<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiActionStatus;
use App\Enums\AiMessageRole;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiDocument;
use App\Models\AiMessage;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    /** Documents attached to one turn; each one is read on demand. */
    private const MAX_DOCUMENTS = 5;

    public function __construct(
        private readonly KernelClient $kernel,
        private readonly SessionService $sessions,
        private readonly CrmToolbox $toolbox,
        private readonly CrmHelpService $help,
    ) {}

    /**
     * @param  array<string, mixed>  $pageContext
     * @param  list<string>  $attachmentIds
     * @param  list<string>  $documentIds
     * @return array<string, mixed>
     */
    public function chat(
        User $user,
        ?string $conversationId,
        string $message,
        array $pageContext = [],
        array $attachmentIds = [],
        array $documentIds = [],
        ?string $projectId = null,
    ): array {
        $message = trim($message);
        $attachments = $this->resolveAttachments($user, $attachmentIds);
        $documents = $this->resolveDocuments($user, $documentIds);

        if ($message === '' && $documents !== []) {
            $message = 'Разбери документ и предложи план задач.';
        }

        if ($message === '' && $attachments !== []) {
            $message = 'Что на изображении?';
        }

        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Сообщение пустое.']);
        }

        $conversation = $this->sessions->resolve($user, $conversationId, $message);

        if ($conversation->project_id !== null
            && ! Project::query()->visibleTo($user)->whereKey($conversation->project_id)->exists()) {
            $conversation->update(['project_id' => null]);
            $conversation->refresh();
        }

        $project = $this->resolveProject($user, $projectId);

        $context = $pageContext;
        $context['generating_at'] = now()->toIso8601String();

        $conversation->update([
            'last_message_at' => now(),
            'context' => $context,
            ...($project !== null ? ['project_id' => $project->id] : []),
        ]);

        $attachmentMeta = array_map(fn (array $file): array => [
            'kind' => 'image',
            'id' => $file['id'],
            'name' => $file['name'],
            'mime' => $file['mime'],
        ], $attachments);

        $documentMeta = array_map(fn (array $document): array => [
            'kind' => 'document',
            'id' => $document['id'],
            'name' => $document['name'],
            'mime' => $document['mime'],
            'chars' => $document['chars'],
            'preview' => $document['preview'],
        ], $documents);

        $this->storeUserMessage($conversation, $message, [...$attachmentMeta, ...$documentMeta]);

        try {
            $result = $this->kernel->chat(
                $user,
                $conversation->id,
                $message,
                $pageContext,
                $attachmentMeta,
                $documentMeta,
            );
        } catch (AiException $exception) {
            $this->clearGenerating($conversation);

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
            'trace' => $result['trace'] ?? null,
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
        $this->markGenerating($conversation);

        try {
            $result = $this->kernel->regenerate($user, $conversation->id, $pageContext);
        } catch (AiException $exception) {
            $this->clearGenerating($conversation);

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
            'trace' => $result['trace'] ?? null,
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
        $data = $user->isAdmin()
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

        return array_values(array_unique([...$this->help->promptSuggestions($user), ...$data]));
    }

    /**
     * @return array{topics: list<array<string, mixed>>, url: string}
     */
    public function help(User $user): array
    {
        return [
            'topics' => array_slice($this->help->forUser($user), 0, 8),
            'url' => route('help.index'),
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

    private function markGenerating(AiConversation $conversation): void
    {
        $context = is_array($conversation->context) ? $conversation->context : [];
        $context['generating_at'] = now()->toIso8601String();
        $conversation->update(['context' => $context]);
    }

    private function clearGenerating(AiConversation $conversation): void
    {
        $context = is_array($conversation->context) ? $conversation->context : [];

        if (! array_key_exists('generating_at', $context)) {
            return;
        }

        unset($context['generating_at']);
        $conversation->update(['context' => $context]);
    }

    /**
     * @param  array<string, mixed>|null  $action
     * @param  array<string, mixed>|null  $overrideAction
     * @return array<string, mixed>
     */
    private function resolveProject(User $user, ?string $projectId): ?Project
    {
        if ($projectId === null || $projectId === '') {
            return null;
        }

        if (! Str::isUuid($projectId)) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        $project = Project::query()
            ->visibleTo($user)
            ->find($projectId);

        if (! $project instanceof Project) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        return $project;
    }

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
                'project' => null,
                'generating' => false,
            ];
        }

        $conversation->refresh()->load(['messages', 'actions', 'project']);
        $conversation->loadCount('messages');

        $messages = $conversation->messages
            ->whereIn('role', [AiMessageRole::User, AiMessageRole::Assistant])
            ->reject(fn (AiMessage $message) => $message->role === AiMessageRole::Assistant && trim((string) $message->content) === '')
            ->values();

        $files = $this->attachmentFiles($messages);
        $documents = $this->documentRecords($messages);

        $pending = $conversation->actions
            ->where('status', AiActionStatus::Proposed)
            ->map(fn (AiAction $item): array => $this->actionPayload($conversation->user, $item))
            ->values()
            ->all();

        $context = is_array($conversation->context) ? $conversation->context : [];
        $generatingAt = $context['generating_at'] ?? null;
        $generating = false;

        if (is_string($generatingAt) && $generatingAt !== '') {
            try {
                $generating = Carbon::parse($generatingAt)->gt(now()->subSeconds(180));
            } catch (\Throwable) {
                $generating = false;
            }

            if (! $generating) {
                unset($context['generating_at']);
                $conversation->update(['context' => $context]);
            }
        }

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
                ->map(fn (AiMessage $message): array => $this->messagePayload($message, $files, $documents))
                ->all(),
            'message' => $assistant !== null ? $this->messagePayload($assistant, $files, $documents) : null,
            'pending_actions' => $pending,
            'pending_action' => $overrideAction !== null
                ? $this->actionPayload($conversation->user, $overrideAction)
                : null,
            'suggestions' => $this->suggestions($conversation->user),
            'generating' => $generating,
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
     * @param  Collection<int, AiMessage>  $messages
     * @return Collection<string, AiDocument>
     */
    private function documentRecords(Collection $messages): Collection
    {
        $ids = $messages
            ->flatMap(fn (AiMessage $message): array => collect($message->attachments ?? [])
                ->filter(fn ($item) => is_array($item) && ($item['kind'] ?? 'image') === 'document')
                ->pluck('id')
                ->filter(fn ($id) => is_string($id))
                ->all())
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return AiDocument::query()->whereIn('id', $ids->all())->get()->keyBy('id');
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, name: string, mime: string, chars: int, preview: string}>
     */
    private function resolveDocuments(User $user, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')));

        if ($ids === []) {
            return [];
        }

        if (count($ids) > self::MAX_DOCUMENTS) {
            throw ValidationException::withMessages([
                'documents' => 'К одному сообщению можно прикрепить не больше '.self::MAX_DOCUMENTS.' документов.',
            ]);
        }

        $documents = AiDocument::query()
            ->whereIn('id', $ids)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($ids as $id) {
            $document = $documents->get($id);

            if (! $document instanceof AiDocument) {
                throw ValidationException::withMessages([
                    'documents' => 'Документ не найден. Загрузите его ещё раз.',
                ]);
            }

            $resolved[] = [
                'id' => $document->id,
                'name' => $document->name,
                'mime' => (string) $document->mime_type,
                'chars' => (int) $document->char_count,
                'preview' => $document->preview(),
            ];
        }

        return $resolved;
    }

    /**
     * @param  Collection<string, FileAttachment>  $files
     * @return array<string, mixed>
     */
    private function messagePayload(AiMessage $message, Collection $files, Collection $documents): array
    {
        $attachments = collect($message->attachments ?? [])
            ->filter(fn ($item) => is_array($item) && isset($item['id']))
            ->map(function (array $item) use ($files, $documents): array {
                $id = (string) $item['id'];

                if (($item['kind'] ?? 'image') === 'document') {
                    $document = $documents->get($id);
                    $file = $document?->file_attachment_id !== null ? $files->get((string) $document->file_attachment_id) : null;

                    return [
                        'kind' => 'document',
                        'id' => $id,
                        'name' => $document?->name ?? (string) ($item['name'] ?? 'документ'),
                        'mime' => $document?->mime_type ?? (string) ($item['mime'] ?? 'application/octet-stream'),
                        'chars' => $document?->char_count ?? (int) ($item['chars'] ?? 0),
                        'preview' => $document?->preview() ?? (string) ($item['preview'] ?? ''),
                        'url' => $file !== null ? route('files.preview', $file) : null,
                        'download_url' => $file !== null ? route('files.download', $file) : null,
                    ];
                }

                $file = $files->get($id);

                return [
                    'kind' => 'image',
                    'id' => $id,
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
            'trace' => $message->trace ?? [],
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
            'type' => $this->toolbox->isFormTool($action->tool_name)
                ? 'form'
                : ($this->toolbox->isPlanTool($action->tool_name) ? 'plan' : 'action'),
            'form' => $this->toolbox->describesForm($action->tool_name)
                ? $this->toolbox->formPayload($user, $action->arguments ?? [])
                : null,
            'plan' => $this->toolbox->describesPlan($action->tool_name)
                ? $this->toolbox->planPayload($user, $action->arguments ?? [])
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
            'propose_task_plan' => 'План задач создан',
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

        if ($tool === 'propose_task_plan' && isset($result['count'])) {
            $lines[] = '- задач: '.$result['count'];

            foreach (array_slice($result['created'] ?? [], 0, 5) as $task) {
                if (isset($task['title'])) {
                    $lines[] = '  - '.$task['title'];
                }
            }

            if (isset($result['project_url'])) {
                $lines[] = '- [Перейти в проект]('.$result['project_url'].')';
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
