<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP client for the central Kiki assistant kernel.
 *
 * CRM keeps ownership of clients, tasks, invoices and confirmations. The
 * kernel owns the model, conversation state and tool loop; it calls back into
 * CRM through `/api/internal/assistant/tools`.
 */
class KernelClient
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<array{id: string, name?: string|null, mime?: string|null}>  $attachments
     * @param  list<array{id: string, name?: string|null, mime?: string|null, chars?: int|null, preview?: string|null}>  $documents
     * @return array<string, mixed>
     */
    public function chat(
        User $user,
        string $conversationId,
        string $message,
        array $context = [],
        array $attachments = [],
        array $documents = [],
    ): array {
        $payload = [
            'channel' => config('ai.kernel_channel', 'crm'),
            'message' => $message,
            'conversation_id' => $conversationId,
            'actor' => [
                'id' => $user->id,
                'role' => $user->role->value,
            ],
            'data_class' => config('ai.kernel_data_class', 'internal'),
            'context' => $context,
        ];

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        if ($documents !== []) {
            $payload['documents'] = array_map(fn (array $document): array => [
                'id' => $document['id'],
                'name' => $document['name'] ?? null,
                'mime' => $document['mime'] ?? null,
                'chars' => $document['chars'] ?? null,
                'preview' => $document['preview'] ?? null,
            ], $documents);
        }

        return $this->send(fn (PendingRequest $request) => $request->post($this->url('/v1/chat'), $payload));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function regenerate(User $user, string $conversationId, array $context = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post(
            $this->url('/v1/conversations/'.rawurlencode($conversationId).'/regenerate'),
            [
                'actor' => ['id' => $user->id, 'role' => $user->role->value],
                'data_class' => config('ai.kernel_data_class', 'internal'),
                'context' => $context,
            ],
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sessions(User $user, ?string $query = null, bool $includeArchived = false, int $limit = 50): array
    {
        $params = [
            'actor_id' => $user->id,
            'limit' => max(1, min($limit, 100)),
        ];

        if ($query !== null && trim($query) !== '') {
            $params['q'] = trim($query);
        }

        if ($includeArchived) {
            $params['archived'] = 'all';
        }

        $payload = $this->send(fn (PendingRequest $request) => $request->get($this->url('/v1/conversations'), $params));

        $sessions = $payload['sessions'] ?? [];

        return is_array($sessions) ? array_values(array_filter($sessions, 'is_array')) : [];
    }

    /**
     * @param  array{title?: string, pinned?: bool, archived?: bool}  $patch
     * @return array<string, mixed>
     */
    public function updateSession(User $user, string $conversationId, array $patch): array
    {
        return $this->send(fn (PendingRequest $request) => $request
            ->withQueryParameters(['actor_id' => $user->id])
            ->patch($this->url('/v1/conversations/'.rawurlencode($conversationId)), $patch));
    }

    /**
     * Start a site study in the harness sidecar. Returns immediately: the CRM
     * settings page polls `study()` and shows the live event stream.
     *
     * @param  array{url: string, focus?: string|null, agents_md?: string|null, instructions?: string|null}  $payload
     * @return array<string, mixed>
     */
    public function startStudy(User $user, array $payload): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post($this->url('/v1/studies'), [
            'actor' => ['id' => $user->id, 'role' => $user->role->value],
            'url' => $payload['url'],
            'focus' => $payload['focus'] ?? null,
            'agents_md' => $payload['agents_md'] ?? null,
            'instructions' => $payload['instructions'] ?? null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function study(User $user, string $id): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get(
            $this->url('/v1/studies/'.rawurlencode($id)),
            ['actor_id' => $user->id],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelStudy(User $user, string $id): array
    {
        return $this->send(fn (PendingRequest $request) => $request
            ->withQueryParameters(['actor_id' => $user->id])
            ->post($this->url('/v1/studies/'.rawurlencode($id).'/cancel')));
    }

    /**
     * Shared price snapshot from the kernel.
     *
     * @return array<string, mixed>
     */
    public function prices(bool $force = false): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get(
            $this->url('/v1/knowledge/pricing'),
            $force ? ['force' => '1'] : [],
        ));
    }

    public function appendNote(string $conversationId, string $content): void
    {
        if (trim($content) === '') {
            return;
        }

        try {
            $this->sendBody(fn (PendingRequest $request) => $request->post(
                $this->url('/v1/conversations/'.rawurlencode($conversationId).'/notes'),
                ['content' => $content],
            ));
        } catch (\Throwable $exception) {
            Log::warning('Kernel note append failed', ['error' => $exception->getMessage()]);
        }
    }

    public function delete(User $user, string $conversationId): void
    {
        try {
            $this->sendBody(fn (PendingRequest $request) => $request
                ->withQueryParameters(['actor_id' => $user->id])
                ->delete($this->url('/v1/conversations/'.rawurlencode($conversationId))));
        } catch (\Throwable $exception) {
            Log::warning('Kernel conversation delete failed', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(User $user, string $conversationId): array
    {
        $payload = $this->send(fn (PendingRequest $request) => $request->get(
            $this->url('/v1/conversations/'.rawurlencode($conversationId)),
        ));

        $messages = $payload['messages'] ?? [];

        return is_array($messages) ? array_values(array_filter($messages, 'is_array')) : [];
    }

    /**
     * Execute a request that returns JSON and map transport failures to one
     * user-facing exception. Callers that can degrade catch AiException.
     *
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $response = $this->sendBody($call);

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function sendBody(callable $call): Response
    {
        try {
            $response = $call($this->request());
        } catch (\Throwable $exception) {
            Log::warning('Kernel request failed', ['error' => $exception->getMessage()]);

            throw new AiException('Ассистент временно недоступен. Попробуйте позже.', 503);
        }

        if ($response->failed()) {
            $status = $response->status() > 0 ? $response->status() : 503;
            $detail = $response->json('detail');

            Log::warning('Kernel request failed', [
                'status' => $status,
                'detail' => $detail,
                'code' => $response->json('code'),
            ]);

            throw new AiException($this->messageFor($status, $detail), $status);
        }

        return $response;
    }

    private function messageFor(int $status, mixed $detail): string
    {
        return match ($status) {
            401, 403 => 'Нет доступа к ассистенту. Проверьте токен канала.',
            404 => 'Диалог не найден. Начните новый.',
            409 => 'Диалог уже изменён. Обновите страницу.',
            422 => is_string($detail) && str_contains($detail, 'text only')
                ? 'Эта модель не умеет читать изображения. Уберите картинку или включите vision для выбранного маршрута модели.'
                : (is_string($detail) && $detail !== ''
                    ? $detail
                    : 'Ассистент не может обработать этот запрос.'),
            503 => 'Локальная модель ассистента недоступна. Настройте локальный endpoint.',
            502, 504 => 'Ассистент временно недоступен. Попробуйте позже.',
            default => is_string($detail) && $detail !== ''
                ? $detail
                : 'Ассистент временно недоступен.',
        };
    }

    private function request(): PendingRequest
    {
        $token = (string) config('ai.kernel_token');
        if ($token === '') {
            throw new AiException('Не настроен KERNEL_TOKEN для CRM.', 503);
        }

        return Http::withToken($token)
            ->withHeaders([
                'X-Kernel-Channel' => (string) config('ai.kernel_channel', 'crm'),
                'X-Correlation-Id' => (string) Str::uuid(),
            ])
            ->acceptJson()
            ->timeout((int) config('ai.kernel_timeout', 90));
    }

    private function url(string $path): string
    {
        $base = (string) config('ai.kernel_url');
        if ($base === '') {
            throw new AiException('Не настроен KERNEL_URL для CRM.', 503);
        }

        return $base.$path;
    }
}
