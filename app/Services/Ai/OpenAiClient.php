<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAiClient
{
    public function __construct(
        private readonly AiSettingsService $settings,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{message: array<string, mixed>, usage: array<string, int>}
     */
    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        if (! $this->settings->isConfigured()) {
            throw new AiException('ИИ ещё не настроен: укажите Base URL, модель и API-ключ в настройках.');
        }

        $payload = [
            'model' => $this->settings->model(),
            'messages' => $messages,
            'temperature' => $this->settings->temperature(),
            'max_tokens' => $this->settings->maxTokens(),
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $payload = array_replace_recursive($payload, $options);

        $response = Http::withToken((string) $this->settings->apiKey())
            ->acceptJson()
            ->asJson()
            ->timeout(90)
            ->post($this->endpoint(), $payload);

        if ($response->failed()) {
            throw new AiException(
                'Провайдер ИИ вернул ошибку '.$response->status().': '.Str::limit($response->body(), 400),
                502,
            );
        }

        $json = $response->json();
        $message = $json['choices'][0]['message'] ?? null;

        if (! is_array($message)) {
            throw new AiException('Провайдер ИИ вернул неожиданный ответ без сообщения.', 502);
        }

        return [
            'message' => $message,
            'usage' => [
                'prompt_tokens' => (int) ($json['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($json['usage']['completion_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function test(): array
    {
        try {
            $result = $this->chat([
                ['role' => 'system', 'content' => 'Ты проверяешь соединение. Ответь одним словом.'],
                ['role' => 'user', 'content' => 'работает?'],
            ], [], ['max_tokens' => 12, 'temperature' => 0]);

            $content = trim((string) ($result['message']['content'] ?? ''));

            return ['ok' => true, 'message' => $content !== '' ? $content : 'Соединение установлено.'];
        } catch (AiException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
    }

    private function endpoint(): string
    {
        $base = $this->settings->baseUrl();

        if (str_ends_with($base, '/chat/completions')) {
            return $base;
        }

        if (str_ends_with($base, '/v1')) {
            return $base.'/chat/completions';
        }

        return $base.'/v1/chat/completions';
    }
}
