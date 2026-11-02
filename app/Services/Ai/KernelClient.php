<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * @return array<string, mixed>
     */
    public function chat(User $user, string $conversationId, string $message, array $context = []): array
    {
        $response = $this->request()
            ->post($this->url('/v1/chat'), [
                'channel' => config('ai.kernel_channel', 'crm'),
                'message' => $message,
                'conversation_id' => $conversationId,
                'actor' => [
                    'id' => $user->id,
                    'role' => $user->role->value,
                ],
                'data_class' => config('ai.kernel_data_class', 'internal'),
                'context' => $context,
            ]);

        if ($response->failed()) {
            $status = $response->status() > 0 ? $response->status() : 503;
            Log::warning('Kernel chat failed', [
                'status' => $status,
                'detail' => $response->json('detail'),
            ]);

            $message = match ($status) {
                401, 403 => 'Нет доступа к ассистенту. Проверьте токен канала.',
                503 => 'Локальная модель ассистента недоступна. Настройте локальный endpoint.',
                502, 504 => 'Ассистент временно недоступен. Попробуйте позже.',
                default => is_string($response->json('detail')) && $response->json('detail') !== ''
                    ? (string) $response->json('detail')
                    : 'Ассистент временно недоступен.',
            };

            throw new AiException($message, $status);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    public function appendNote(string $conversationId, string $content): void
    {
        if (trim($content) === '') {
            return;
        }

        try {
            $response = $this->request()
                ->post($this->url('/v1/conversations/'.rawurlencode($conversationId).'/notes'), [
                    'content' => $content,
                ]);

            if ($response->failed()) {
                Log::warning('Kernel note append failed', ['status' => $response->status()]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Kernel note append failed', ['error' => $exception->getMessage()]);
        }
    }

    public function delete(string $conversationId): void
    {
        try {
            $this->request()->delete($this->url('/v1/conversations/'.rawurlencode($conversationId)));
        } catch (\Throwable $exception) {
            Log::warning('Kernel conversation delete failed', ['error' => $exception->getMessage()]);
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $token = (string) config('ai.kernel_token');
        if ($token === '') {
            throw new AiException('Не настроен KERNEL_TOKEN для CRM.', 503);
        }

        return Http::withToken($token)
            ->withHeaders(['X-Kernel-Channel' => (string) config('ai.kernel_channel', 'crm')])
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
