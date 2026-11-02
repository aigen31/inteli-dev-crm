<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiActionStatus;
use App\Enums\AiMessageRole;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AgentService
{
    private const WRITE_TOOLS = [
        'create_client',
        'create_task',
        'update_task_status',
        'apply_task_template',
        'create_task_template',
        'create_invoice',
        'mark_invoice_paid',
        'set_custom_field',
    ];

    public function __construct(
        private readonly OpenAiClient $client,
        private readonly CrmToolbox $toolbox,
        private readonly ContextBuilder $context,
        private readonly PromptFactory $prompts,
    ) {}

    /**
     * @param  array<string, mixed>  $pageContext
     * @return array<string, mixed>
     */
    public function chat(User $user, ?string $conversationId, string $message, array $pageContext = []): array
    {
        $message = trim($message);

        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Сообщение пустое.']);
        }

        $conversation = $this->conversation($user, $conversationId, $message);
        $conversation->update([
            'last_message_at' => now(),
            'context' => $pageContext,
        ]);

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessageRole::User,
            'content' => $message,
        ]);

        $messages = [
            ['role' => 'system', 'content' => $this->prompts->system($user)],
            ['role' => 'system', 'content' => $this->context->for($user, $pageContext)],
            ...$this->historyMessages($conversation),
        ];

        $tools = $this->toolbox->definitions($user);
        $pendingAction = null;

        for ($step = 0; $step < 6; $step++) {
            try {
                $response = $this->client->chat($messages, $tools);
            } catch (AiException $exception) {
                $assistant = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessageRole::Assistant,
                    'content' => $exception->getMessage(),
                ]);

                return $this->payload($conversation, $assistant, null, $exception->status);
            }

            $assistantMessage = $response['message'];
            $toolCalls = $assistantMessage['tool_calls'] ?? [];
            $content = $assistantMessage['content'] ?? null;

            if (! is_array($toolCalls) || $toolCalls === []) {
                $assistant = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessageRole::Assistant,
                    'content' => (string) ($content ?? ''),
                    'tokens_in' => $response['usage']['prompt_tokens'] ?? 0,
                    'tokens_out' => $response['usage']['completion_tokens'] ?? 0,
                ]);

                return $this->payload($conversation, $assistant);
            }

            AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $content,
                'tool_calls' => $toolCalls,
                'tokens_in' => $response['usage']['prompt_tokens'] ?? 0,
                'tokens_out' => $response['usage']['completion_tokens'] ?? 0,
            ]);

            $messages[] = [
                'role' => 'assistant',
                'content' => $content,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                $callId = (string) ($call['id'] ?? '');
                $arguments = $this->decodeArguments($call['function']['arguments'] ?? '{}');

                if ($this->toolbox->requiresConfirmation($name)) {
                    $action = AiAction::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'tool_name' => $name,
                        'arguments' => $arguments,
                        'status' => AiActionStatus::Proposed,
                        'requires_confirmation' => true,
                    ]);

                    $pendingAction ??= $action;

                    $toolResult = ['status' => 'awaiting_confirmation', 'action_id' => $action->id];
                    AiMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => AiMessageRole::Tool,
                        'tool_call_id' => $callId,
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    ]);
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $callId,
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    ];

                    continue;
                }

                try {
                    $toolResult = $this->toolbox->execute($name, $user, $arguments);
                    $status = AiActionStatus::Executed;
                } catch (\Throwable $exception) {
                    $toolResult = ['error' => $exception->getMessage()];
                    $status = AiActionStatus::Failed;
                    Log::warning('AI tool failed', [
                        'tool' => $name,
                        'user_id' => $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }

                if (in_array($name, self::WRITE_TOOLS, true)) {
                    AiAction::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'tool_name' => $name,
                        'arguments' => $arguments,
                        'result' => $toolResult,
                        'status' => $status,
                        'requires_confirmation' => false,
                        'executed_at' => $status === AiActionStatus::Executed ? now() : null,
                        'error' => $status === AiActionStatus::Failed ? ($toolResult['error'] ?? null) : null,
                    ]);
                }

                $encoded = json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessageRole::Tool,
                    'tool_call_id' => $callId,
                    'content' => $encoded,
                ]);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'content' => $encoded,
                ];
            }

            if ($pendingAction !== null) {
                $summary = $this->toolbox->summarize($pendingAction->tool_name, $pendingAction->arguments);
                $assistant = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessageRole::Assistant,
                    'content' => "Я подготовила действие: **{$summary}**.\n\nПроверьте детали и подтвердите — тогда я выполню его.",
                ]);

                return $this->payload($conversation, $assistant, $pendingAction);
            }
        }

        $assistant = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessageRole::Assistant,
            'content' => 'Я сделала несколько шагов, но задача оказалась сложнее. Уточните, пожалуйста, что важнее сделать в первую очередь?',
        ]);

        return $this->payload($conversation, $assistant);
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(User $user, AiAction $action): array
    {
        $this->assertActionOwnership($user, $action);

        try {
            $result = $this->toolbox->execute($action->tool_name, $user, $action->arguments);
        } catch (\Throwable $exception) {
            $action->update([
                'status' => AiActionStatus::Failed,
                'error' => $exception->getMessage(),
                'confirmed_at' => now(),
            ]);

            throw $exception;
        }

        $action->update([
            'status' => AiActionStatus::Executed,
            'result' => $result,
            'confirmed_at' => now(),
            'executed_at' => now(),
        ]);

        $conversation = $action->conversation;
        $assistant = null;

        if ($conversation !== null) {
            $assistant = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $this->formatActionResult($action->tool_name, $result),
            ]);
        }

        return $this->payload($conversation, $assistant);
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(User $user, AiAction $action): array
    {
        $this->assertActionOwnership($user, $action);

        $action->update([
            'status' => AiActionStatus::Rejected,
            'confirmed_at' => now(),
        ]);

        $conversation = $action->conversation;
        $assistant = null;

        if ($conversation !== null) {
            $assistant = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => 'Хорошо, действие отменено. Ничего не изменилось.',
            ]);
        }

        return $this->payload($conversation, $assistant);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(User $user): ?array
    {
        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->latest('last_message_at')
            ->first();

        return $conversation !== null ? $this->payload($conversation) : null;
    }

    public function clear(User $user): void
    {
        DB::transaction(function () use ($user): void {
            AiConversation::query()->where('user_id', $user->id)->delete();
        });
    }

    /**
     * @return list<string>
     */
    public function suggestions(User $user): array
    {
        return $user->isAdmin()
            ? [
                'Что сегодня в работе?',
                'Кто ждёт счёт?',
                'Сколько я заработал в этом месяце?',
                'Создай задачу из шаблона',
                'Какие заявки пришли на этой неделе?',
            ]
            : [
                'Что с моими задачами?',
                'Есть неоплаченные счета?',
                'Создай заявку по шаблону',
                'Когда ближайший срок?',
                'Покажи мои последние счета',
            ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        ?AiConversation $conversation,
        ?AiMessage $assistant = null,
        ?AiAction $action = null,
        ?int $errorStatus = null,
    ): array {
        if ($conversation === null) {
            return ['messages' => [], 'pending_actions' => [], 'suggestions' => []];
        }

        $conversation->refresh()->load(['messages', 'actions']);

        $messages = $conversation->messages
            ->whereIn('role', [AiMessageRole::User, AiMessageRole::Assistant])
            ->reject(fn (AiMessage $message) => $message->role === AiMessageRole::Assistant && trim((string) $message->content) === '')
            ->map(fn (AiMessage $message) => [
                'id' => $message->id,
                'role' => $message->role->value,
                'content' => $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $pending = $conversation->actions
            ->where('status', AiActionStatus::Proposed)
            ->map(fn (AiAction $item) => [
                'id' => $item->id,
                'tool_name' => $item->tool_name,
                'summary' => $this->toolbox->summarize($item->tool_name, $item->arguments),
                'arguments' => $item->arguments,
                'created_at' => $item->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'conversation_id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $messages,
            'message' => $assistant !== null ? [
                'id' => $assistant->id,
                'role' => $assistant->role->value,
                'content' => $assistant->content,
                'created_at' => $assistant->created_at?->toIso8601String(),
            ] : null,
            'pending_actions' => $pending,
            'pending_action' => $action !== null ? [
                'id' => $action->id,
                'tool_name' => $action->tool_name,
                'summary' => $this->toolbox->summarize($action->tool_name, $action->arguments),
                'arguments' => $action->arguments,
            ] : null,
            'suggestions' => $this->suggestions($conversation->user),
            'error_status' => $errorStatus,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historyMessages(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->latest()
            ->limit(40)
            ->get()
            ->reverse()
            ->map(function (AiMessage $message): ?array {
                return match ($message->role) {
                    AiMessageRole::User => ['role' => 'user', 'content' => $message->content],
                    AiMessageRole::Assistant => [
                        'role' => 'assistant',
                        'content' => $message->content,
                        ...($message->tool_calls !== null ? ['tool_calls' => $message->tool_calls] : []),
                    ],
                    AiMessageRole::Tool => [
                        'role' => 'tool',
                        'tool_call_id' => $message->tool_call_id,
                        'content' => $message->content,
                    ],
                    default => null,
                };
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArguments(mixed $arguments): array
    {
        if (is_array($arguments)) {
            return $arguments;
        }

        if (! is_string($arguments) || trim($arguments) === '') {
            return [];
        }

        try {
            $decoded = json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    private function conversation(User $user, ?string $id, string $message): AiConversation
    {
        if ($id !== null && $id !== '') {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->find($id);

            if ($conversation instanceof AiConversation) {
                return $conversation;
            }
        }

        return AiConversation::create([
            'user_id' => $user->id,
            'title' => $this->prompts->conversationTitle($message),
            'last_message_at' => now(),
        ]);
    }

    private function assertActionOwnership(User $user, AiAction $action): void
    {
        if ($action->user_id !== $user->id) {
            abort(403);
        }

        if ($action->status !== AiActionStatus::Proposed) {
            throw ValidationException::withMessages(['action' => 'Это действие уже обработано.']);
        }

        if ($action->created_at !== null && $action->created_at->lt(now()->subMinutes(30))) {
            $action->update(['status' => AiActionStatus::Failed, 'error' => 'Истекло время подтверждения.']);

            throw ValidationException::withMessages(['action' => 'Время подтверждения истекло. Попросите Кики подготовить действие снова.']);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatActionResult(string $tool, array $result): string
    {
        $title = match ($tool) {
            'create_invoice' => 'Счёт создан',
            'mark_invoice_paid' => 'Оплата подтверждена',
            'create_client' => 'Клиент создан',
            'create_task', 'apply_task_template' => 'Задача создана',
            'create_task_template' => 'Шаблон создан',
            'set_custom_field' => 'Поле обновлено',
            default => 'Готово',
        };

        $lines = ["**{$title}.**"];

        foreach (['title', 'name', 'amount', 'status_label', 'display', 'email'] as $key) {
            if (isset($result[$key]) && $result[$key] !== '') {
                $lines[] = '- '.$key.': '.$result[$key];
            }
        }

        if (isset($result['id'])) {
            $lines[] = '- ID: `'.$result['id'].'`';
        }

        return implode("\n", $lines);
    }
}
