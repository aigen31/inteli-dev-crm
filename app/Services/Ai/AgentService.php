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

/**
 * CRM adapter for the central Kiki assistant kernel.
 *
 * The kernel owns model calls, tool loop, conversation memory and safety
 * policy. CRM keeps the user-facing audit mirror, pending actions and the
 * confirmation flow, because those are domain state.
 */
class AgentService
{
    public function __construct(
        private readonly KernelClient $kernel,
        private readonly CrmToolbox $toolbox,
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

        try {
            $result = $this->kernel->chat($user, $conversation->id, $message, $pageContext);
        } catch (AiException $exception) {
            $assistant = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $exception->getMessage(),
            ]);

            return $this->payload($conversation, $assistant, null, $exception->status);
        }

        $answer = trim((string) ($result['answer'] ?? ''));
        $pending = null;

        $pendingId = $result['pending_action']['id'] ?? null;
        if (is_string($pendingId) && $pendingId !== '') {
            $candidate = AiAction::query()->find($pendingId);
            if ($candidate instanceof AiAction && $candidate->user_id === $user->id) {
                $pending = $candidate;
            }
        }

        if ($pending !== null) {
            $summary = $this->toolbox->summarize($pending->tool_name, $pending->arguments);
            if ($answer === '') {
                $answer = "Я подготовила действие: **{$summary}**.\n\nПроверьте детали и подтвердите — тогда я выполню его.";
            }
        }

        if ($answer === '') {
            $answer = 'Не удалось получить ответ. Попробуйте переформулировать вопрос.';
        }

        $assistant = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessageRole::Assistant,
            'content' => $answer,
            'tokens_in' => (int) ($result['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($result['usage']['completion_tokens'] ?? 0),
        ]);

        return $this->payload($conversation, $assistant, $pending);
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
            $this->kernel->appendNote($conversation->id, (string) $assistant->content);
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
            $this->kernel->appendNote($conversation->id, (string) $assistant->content);
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
        $ids = AiConversation::query()->where('user_id', $user->id)->pluck('id')->all();

        DB::transaction(function () use ($user): void {
            AiConversation::query()->where('user_id', $user->id)->delete();
        });

        foreach ($ids as $id) {
            $this->kernel->delete((string) $id);
        }
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
            'title' => mb_substr(trim(preg_replace('/\s+/u', ' ', $message) ?? $message), 0, 80) ?: 'Новый диалог',
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
