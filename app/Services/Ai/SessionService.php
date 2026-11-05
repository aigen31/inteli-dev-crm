<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiActionStatus;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Session list and metadata for the assistant workspace.
 *
 * The kernel owns session lifecycle: list, title, pin, archive, delete. CRM
 * mirrors the result so `ai_actions` keeps its foreign key and so the sidebar
 * still renders while the kernel is briefly unavailable. The mirror never wins
 * over a successful kernel response.
 */
class SessionService
{
    public function __construct(
        private readonly KernelClient $kernel,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(User $user, ?string $search = null, bool $includeArchived = false): array
    {
        $pending = $this->pendingCounts($user);

        try {
            $sessions = $this->kernel->sessions($user, $search, $includeArchived);

            return array_values(array_map(
                fn (array $session): array => $this->normalizeKernel($session, $pending),
                $sessions,
            ));
        } catch (AiException $exception) {
            Log::warning('Assistant session list fell back to the local mirror', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->localList($user, $search, $includeArchived, $pending);
        }
    }

    /**
     * Reserve an id for a new chat. The row is created with the first message
     * so an abandoned "new chat" does not leave an empty session behind.
     */
    public function reserveId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Resolve the conversation a chat turn belongs to, creating the local
     * mirror row on the first message.
     */
    public function resolve(User $user, ?string $conversationId, string $firstMessage): AiConversation
    {
        if ($conversationId !== null && $conversationId !== '') {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->find($conversationId);

            if ($conversation instanceof AiConversation) {
                return $conversation;
            }

            // An id that exists for another user must not be silently reused.
            if (AiConversation::query()->whereKey($conversationId)->exists()) {
                abort(404);
            }
        }

        return AiConversation::create([
            'id' => $conversationId !== null && $conversationId !== ''
                ? $conversationId
                : $this->reserveId(),
            'user_id' => $user->id,
            'title' => $this->titleFor($firstMessage),
            'last_message_at' => now(),
        ]);
    }

    /**
     * @param  array{title?: string, pinned?: bool, archived?: bool}  $patch
     * @return array<string, mixed>
     */
    public function update(User $user, AiConversation $conversation, array $patch): array
    {
        $session = $this->kernel->updateSession($user, $conversation->id, $patch);

        $this->mirror($conversation, $session);

        return $this->normalizeKernel($session, $this->pendingCounts($user));
    }

    public function delete(User $user, AiConversation $conversation): void
    {
        $this->kernel->delete($user, $conversation->id);

        DB::transaction(function () use ($conversation): void {
            $conversation->actions()->delete();
            $conversation->messages()->delete();
            $conversation->delete();
        });
    }

    public function clear(User $user): void
    {
        $conversations = AiConversation::query()->where('user_id', $user->id)->get();

        foreach ($conversations as $conversation) {
            $this->kernel->delete($user, $conversation->id);
        }

        DB::transaction(function () use ($user): void {
            AiAction::query()->where('user_id', $user->id)->delete();
            AiMessage::query()
                ->whereIn('conversation_id', AiConversation::query()->select('id')->where('user_id', $user->id))
                ->delete();
            AiConversation::query()->where('user_id', $user->id)->delete();
        });
    }

    /**
     * Keep the local mirror aligned with a kernel chat response.
     *
     * @param  array<string, mixed>  $result
     */
    public function syncFromChat(AiConversation $conversation, array $result): void
    {
        $title = $result['title'] ?? null;
        $patch = [];

        if (is_string($title) && trim($title) !== '') {
            $patch['title'] = mb_substr(trim($title), 0, 80);
        }

        $context = is_array($conversation->context) ? $conversation->context : [];

        if (isset($result['route']) && is_string($result['route'])) {
            $context['route'] = $result['route'];
        }

        if (isset($result['model']) && is_string($result['model'])) {
            $context['model'] = $result['model'];
        }

        unset($context['generating_at']);

        $patch['context'] = $context;
        $patch['last_message_at'] = now();

        $conversation->update($patch);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(AiConversation $conversation): array
    {
        $conversation->loadCount('messages');

        return [
            'id' => $conversation->id,
            'title' => $this->displayTitle($conversation->title),
            'preview' => '',
            'message_count' => (int) ($conversation->messages_count ?? 0),
            'pinned' => $conversation->isPinned(),
            'archived' => $conversation->isArchived(),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => ($conversation->last_message_at ?? $conversation->updated_at)?->toIso8601String(),
            'pending_actions' => $conversation->actions()
                ->where('status', AiActionStatus::Proposed)
                ->count(),
            'route' => is_array($conversation->context) ? ($conversation->context['route'] ?? null) : null,
            'model' => is_array($conversation->context) ? ($conversation->context['model'] ?? null) : null,
            'source' => 'local',
        ];
    }

    public function titleFor(string $message): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $message));

        return mb_substr($title, 0, 80) ?: 'Новый диалог';
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function mirror(AiConversation $conversation, array $session): void
    {
        $title = $session['title'] ?? null;

        $conversation->update([
            'title' => is_string($title) && trim($title) !== ''
                ? mb_substr(trim($title), 0, 80)
                : $conversation->title,
            'pinned_at' => ($session['pinned'] ?? false) ? ($conversation->pinned_at ?? now()) : null,
            'archived_at' => ($session['archived'] ?? false) ? ($conversation->archived_at ?? now()) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  array<string, int>  $pending
     * @return array<string, mixed>
     */
    private function normalizeKernel(array $session, array $pending): array
    {
        $id = (string) ($session['id'] ?? '');

        return [
            'id' => $id,
            'title' => $this->displayTitle((string) ($session['title'] ?? '')),
            'preview' => $this->preview((string) ($session['preview'] ?? '')),
            'message_count' => (int) ($session['message_count'] ?? 0),
            'pinned' => (bool) ($session['pinned'] ?? false),
            'archived' => (bool) ($session['archived'] ?? false),
            'created_at' => $session['created_at'] ?? null,
            'updated_at' => $session['updated_at'] ?? null,
            'pending_actions' => $pending[$id] ?? 0,
            'source' => 'kernel',
        ];
    }

    /**
     * @param  array<string, int>  $pending
     * @return list<array<string, mixed>>
     */
    private function localList(User $user, ?string $search, bool $includeArchived, array $pending): array
    {
        $search = $search !== null ? trim($search) : '';

        $conversations = AiConversation::query()
            ->where('user_id', $user->id)
            ->when(! $includeArchived, fn ($query) => $query->whereNull('archived_at'))
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhereHas('messages', fn ($messages) => $messages->where('content', 'like', "%{$search}%"));
            }))
            ->withCount('messages')
            ->orderByRaw('pinned_at is null')
            ->orderByDesc('pinned_at')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return $conversations->map(function (AiConversation $conversation) use ($pending): array {
            $last = $conversation->messages()
                ->whereIn('role', ['user', 'assistant'])
                ->orderByDesc('created_at')
                ->value('content');

            return [
                'id' => $conversation->id,
                'title' => $this->displayTitle($conversation->title),
                'preview' => $this->preview((string) ($last ?? '')),
                'message_count' => (int) ($conversation->messages_count ?? 0),
                'pinned' => $conversation->isPinned(),
                'archived' => $conversation->isArchived(),
                'created_at' => $conversation->created_at?->toIso8601String(),
                'updated_at' => ($conversation->last_message_at ?? $conversation->updated_at)?->toIso8601String(),
                'pending_actions' => $pending[$conversation->id] ?? 0,
                'source' => 'local',
            ];
        })->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function pendingCounts(User $user): array
    {
        /** @var Collection<string, int> $counts */
        $counts = AiAction::query()
            ->where('user_id', $user->id)
            ->where('status', AiActionStatus::Proposed)
            ->whereNotNull('conversation_id')
            ->selectRaw('conversation_id, count(*) as aggregate')
            ->groupBy('conversation_id')
            ->pluck('aggregate', 'conversation_id');

        return $counts->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])->all();
    }

    private function displayTitle(string $title): string
    {
        $title = trim($title);

        return $title !== '' ? $title : 'Новый диалог';
    }

    private function preview(string $content): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($content)));

        return mb_substr($plain, 0, 120);
    }
}
