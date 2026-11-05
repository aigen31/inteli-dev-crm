<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiActionStatus;
use App\Enums\AiMessageRole;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\FileAttachment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * CRM adapter for the central Kiki assistant kernel.
 *
 * The kernel owns model calls, tool loop, conversation memory, session
 * metadata and image handling. CRM keeps the user-facing audit mirror, pending
 * actions, uploaded files and the confirmation flow, because those are domain
 * state.
 */
class AgentService
{
    /** Keep in sync with the kernel ceiling for one user turn. */
    private const MAX_ATTACHMENTS = 4;

    /** Keep in sync with `KERNEL_MAX_ATTACHMENT_BYTES` (8 MB by default). */
    private const MAX_ATTACHMENT_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private readonly KernelClient $kernel,
        private readonly SessionService $sessions,
        private readonly CrmToolbox $toolbox,
    ) {}

    /**
     * @param  array<string, mixed>  $pageContext
     * @param  list<string>  $attachmentIds
     * @return array<string, mixed>
     */
    public function chat(
        User $user,
        ?string $conversationId,
        string $message,
        array $pageContext = [],
        array $attachmentIds = [],
    ): array {
        $message = trim($message);
        $attachments = $this->resolveAttachments($user, $attachmentIds);

        if ($message === '' && $attachments !== []) {
            $message = 'Что на изображении?';
        }

        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Сообщение пустое.']);
        }

        $conversation = $this->sessions->resolve($user, $conversationId, $message);
        $conversation->update([
            'last_message_at' => now(),
            'context' => $pageContext,
        ]);

        $attachmentMeta = array_map(fn (array $file): array => [
            'id' => $file['id'],
            'name' => $file['name'],
            'mime' => $file['mime'],
        ], $attachments);

        $this->storeUserMessage($conversation, $message, $attachmentMeta);

        try {
            $result = $this->kernel->chat($user, $conversation->id, $message, $pageContext, $attachmentMeta);
        } catch (AiException $exception) {
            $assistant = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $exception->getMessage(),
            ]);

            return $this->payload($conversation, $assistant, null, $exception->status);
        }

        $this->sessions->syncFromChat($conversation, $result);

        $answer = trim((string) ($result['answer'] ?? ''));
        $pending = $this->pendingFromResult($user, $result);

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
     * Regenerate the last answer. Local mirror drops the trailing assistant
     * messages first, then the kernel re-runs the same user turn.
     *
     * @param  array<string, mixed>  $pageContext
     * @return array<string, mixed>
     */
    public function regenerate(User $user, AiConversation $conversation, array $pageContext = []): array
    {
        $this->dropTrailingAnswer($conversation);

        try {
            $result = $this->kernel->regenerate($user, $conversation->id, $pageContext);
        } catch (AiException $exception) {
            $assistant = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $exception->getMessage(),
            ]);

            return $this->payload($conversation, $assistant, null, $exception->status);
        }

        $this->sessions->syncFromChat($conversation, $result);
        $pending = $this->pendingFromResult($user, $result);

        $answer = trim((string) ($result['answer'] ?? ''));
        if ($answer === '') {
            $answer = 'Не удалось получить ответ. Попробуйте ещё раз.';
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function confirm(User $user, AiAction $action, array $overrides = []): array
    {
        $this->assertActionOwnership($user, $action);

        if ($overrides !== []) {
            if (! $this->toolbox->allowsArgumentOverride($action->tool_name)) {
                throw ValidationException::withMessages(['action' => 'Это действие нельзя изменить перед подтверждением.']);
            }

            $action->update(['arguments' => array_replace($action->arguments ?? [], $overrides)]);
        }

        try {
            $result = $this->toolbox->execute($action->tool_name, $user, $action->arguments ?? []);
        } catch (ValidationException $exception) {
            // The form did not pass domain validation. Keep the action proposed
            // so the user can fix the fields and confirm again.
            throw $exception;
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
            $content = $this->formatActionResult($action->tool_name, $result, $user);
            $assistant = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $content,
            ]);
            $this->kernel->appendNote($conversation->id, $content);
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

    /**
     * @return array<string, mixed>
     */
    public function sessionPayload(User $user, AiConversation $conversation): array
    {
        return $this->payload($conversation);
    }

    public function clear(User $user): void
    {
        $this->sessions->clear($user);
    }

    /**
     * @return list<string>
     */
    public function suggestions(User $user): array
    {
        return $user->isAdmin()
            ? [
                'Что сегодня в работе?',
                'Оформить заявку по форме',
                'Кто ждёт счёт?',
                'Сколько я заработал в этом месяце?',
                'Посмотри скриншот — что не так?',
            ]
            : [
                'Что с моими задачами?',
                'Оформить заявку по форме',
                'Есть неоплаченные счета?',
                'Прикрепи скриншот и опиши проблему',
                'Когда ближайший срок?',
            ];
    }

    /**
     * @param  list<array{id: string, name?: string|null, mime?: string|null}>  $meta
     */
    private function storeUserMessage(AiConversation $conversation, string $message, array $meta): void
    {
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessageRole::User,
            'content' => $message,
            'attachments' => $meta !== [] ? $meta : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function pendingFromResult(User $user, array $result): ?AiAction
    {
        $pendingId = $result['pending_action']['id'] ?? null;

        if (! is_string($pendingId) || $pendingId === '') {
            return null;
        }

        $candidate = AiAction::query()->find($pendingId);

        return $candidate instanceof AiAction && $candidate->user_id === $user->id
            ? $candidate
            : null;
    }

    /**
     * @param  array<string, mixed>|null  $action
     * @param  array<string, mixed>|null  $overrideAction
     * @return array<string, mixed>
     */
    private function payload(
        ?AiConversation $conversation,
        ?AiMessage $assistant = null,
        ?AiAction $overrideAction = null,
        ?int $errorStatus = null,
    ): array {
        if ($conversation === null) {
            return [
                'conversation_id' => null,
                'session' => null,
                'messages' => [],
                'message' => null,
                'pending_actions' => [],
                'pending_action' => null,
                'suggestions' => [],
            ];
        }

        $conversation->refresh()->load(['messages', 'actions']);
        $conversation->loadCount('messages');

        $messages = $conversation->messages
            ->whereIn('role', [AiMessageRole::User, AiMessageRole::Assistant])
            ->reject(fn (AiMessage $message) => $message->role === AiMessageRole::Assistant && trim((string) $message->content) === '')
            ->values();

        $files = $this->attachmentFiles($messages);

        $pending = $conversation->actions
            ->where('status', AiActionStatus::Proposed)
            ->map(fn (AiAction $item): array => $this->actionPayload($conversation->user, $item))
            ->values()
            ->all();

        return [
            'conversation_id' => $conversation->id,
            'session' => [
                'id' => $conversation->id,
                'title' => $this->displayTitle($conversation->title),
                'pinned' => $conversation->isPinned(),
                'archived' => $conversation->isArchived(),
                'message_count' => (int) ($conversation->messages_count ?? $messages->count()),
            ],
            'messages' => $messages
                ->map(fn (AiMessage $message): array => $this->messagePayload($message, $files))
                ->all(),
            'message' => $assistant !== null ? $this->messagePayload($assistant, $files) : null,
            'pending_actions' => $pending,
            'pending_action' => $overrideAction !== null
                ? $this->actionPayload($conversation->user, $overrideAction)
                : null,
            'suggestions' => $this->suggestions($conversation->user),
            'error_status' => $errorStatus,
        ];
    }

    /**
     * @param  Collection<int, AiMessage>  $messages
     * @return Collection<string, FileAttachment>
     */
    private function attachmentFiles(Collection $messages): Collection
    {
        $ids = $messages
            ->flatMap(fn (AiMessage $message): array => collect($message->attachments ?? [])
                ->pluck('id')
                ->filter(fn ($id) => is_string($id))
                ->all())
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return FileAttachment::query()->whereIn('id', $ids->all())->get()->keyBy('id');
    }

    /**
     * @param  Collection<string, FileAttachment>  $files
     * @return array<string, mixed>
     */
    private function messagePayload(AiMessage $message, Collection $files): array
    {
        $attachments = collect($message->attachments ?? [])
            ->filter(fn ($item) => is_array($item) && isset($item['id']))
            ->map(function (array $item) use ($files): array {
                $file = $files->get((string) $item['id']);

                return [
                    'id' => (string) $item['id'],
                    'name' => $file?->original_name ?? (string) ($item['name'] ?? 'изображение'),
                    'mime' => $file?->mime_type ?? (string) ($item['mime'] ?? 'image/*'),
                    'size' => $file?->size,
                    'url' => $file !== null ? route('files.preview', $file) : null,
                    'download_url' => $file !== null ? route('files.download', $file) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'content' => (string) $message->content,
            'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionPayload(User $user, AiAction $action): array
    {
        return [
            'id' => $action->id,
            'tool_name' => $action->tool_name,
            'summary' => $this->toolbox->summarize($action->tool_name, $action->arguments),
            'arguments' => $action->arguments,
            'type' => $this->toolbox->isFormTool($action->tool_name) ? 'form' : 'action',
            'form' => $this->toolbox->describesForm($action->tool_name)
                ? $this->toolbox->formPayload($user, $action->arguments ?? [])
                : null,
            'created_at' => $action->created_at?->toIso8601String(),
        ];
    }

    private function dropTrailingAnswer(AiConversation $conversation): void
    {
        $messages = $conversation->messages()
            ->whereIn('role', [AiMessageRole::User, AiMessageRole::Assistant])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $lastUserIndex = null;
        foreach ($messages as $index => $message) {
            if ($message->role === AiMessageRole::User) {
                $lastUserIndex = $index;
            }
        }

        if ($lastUserIndex === null) {
            throw ValidationException::withMessages([
                'conversation' => 'В этом диалоге ещё нет вопроса, на который можно ответить заново.',
            ]);
        }

        $tail = $messages->slice($lastUserIndex + 1)->pluck('id')->all();

        if ($tail !== []) {
            AiMessage::query()->whereIn('id', $tail)->delete();
        }
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, name: string, mime: string}>
     */
    private function resolveAttachments(User $user, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')));

        if ($ids === []) {
            return [];
        }

        if (count($ids) > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages([
                'attachments' => 'К одному сообщению можно прикрепить не больше '.self::MAX_ATTACHMENTS.' изображений.',
            ]);
        }

        $files = FileAttachment::query()
            ->whereIn('id', $ids)
            ->where('uploaded_by', $user->id)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($ids as $id) {
            $file = $files->get($id);

            if (! $file instanceof FileAttachment) {
                throw ValidationException::withMessages([
                    'attachments' => 'Файл не найден. Загрузите изображение ещё раз.',
                ]);
            }

            if (! str_starts_with((string) $file->mime_type, 'image/')) {
                throw ValidationException::withMessages([
                    'attachments' => 'В чат можно прикреплять только изображения.',
                ]);
            }

            if ((int) $file->size > self::MAX_ATTACHMENT_BYTES) {
                throw ValidationException::withMessages([
                    'attachments' => 'Изображение больше 8 МБ. Сожмите его или сделайте скриншот меньшего размера.',
                ]);
            }

            $resolved[] = [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime' => (string) $file->mime_type,
            ];
        }

        return $resolved;
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
    private function formatActionResult(string $tool, array $result, User $user): string
    {
        $title = match ($tool) {
            'create_invoice' => 'Счёт создан',
            'mark_invoice_paid' => 'Оплата подтверждена',
            'create_client' => 'Клиент создан',
            'create_task', 'apply_task_template' => 'Задача создана',
            'create_task_from_form' => 'Заявка собрана из формы',
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

            if (in_array($tool, ['create_task', 'create_task_from_form', 'apply_task_template'], true)) {
                $url = $user->isAdmin()
                    ? route('admin.tasks.show', $result['id'])
                    : route('client.tasks.show', $result['id']);
                $lines[] = '- [Открыть задачу]('.$url.')';
            }
        }

        return implode("\n", $lines);
    }

    private function displayTitle(?string $title): string
    {
        $title = trim((string) $title);

        return $title !== '' ? $title : 'Новый диалог';
    }
}
