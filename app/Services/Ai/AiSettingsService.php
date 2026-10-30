<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class AiSettingsService
{
    private ?array $values = null;

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->values ??= Setting::query()
            ->whereIn('key', [
                'ai_enabled',
                'ai_base_url',
                'ai_api_key',
                'ai_model',
                'ai_temperature',
                'ai_max_tokens',
            ])
            ->pluck('value', 'key')
            ->all();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || trim($value) === '' ? $default : $value;
    }

    public function isEnabled(): bool
    {
        return $this->get('ai_enabled', '1') !== '0';
    }

    public function baseUrl(): string
    {
        return rtrim($this->get('ai_base_url', (string) env('AI_BASE_URL', 'https://api.openai.com/v1')) ?? '', '/');
    }

    public function model(): string
    {
        return $this->get('ai_model', (string) env('AI_MODEL', 'gpt-4o-mini')) ?? 'gpt-4o-mini';
    }

    public function temperature(): float
    {
        return (float) ($this->get('ai_temperature', (string) env('AI_TEMPERATURE', '0.4')) ?? 0.4);
    }

    public function maxTokens(): int
    {
        return (int) ($this->get('ai_max_tokens', (string) env('AI_MAX_TOKENS', '1200')) ?? 1200);
    }

    public function apiKey(): ?string
    {
        $stored = $this->get('ai_api_key');

        if ($stored !== null) {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                return $stored;
            }
        }

        $env = env('AI_API_KEY');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && $this->apiKey() !== null
            && $this->baseUrl() !== ''
            && $this->model() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        $base = $this->baseUrl();

        return [
            'enabled' => $this->isEnabled(),
            'is_configured' => $this->isConfigured(),
            'base_url' => $base,
            'model' => $this->model(),
            'temperature' => $this->temperature(),
            'max_tokens' => $this->maxTokens(),
            'has_key' => $this->apiKey() !== null,
            'key_source' => $this->get('ai_api_key') !== null ? 'settings' : ($this->apiKey() !== null ? 'env' : null),
            'provider' => parse_url($base, PHP_URL_HOST) ?: $base,
        ];
    }

    /**
     * @param  array{enabled?: bool, base_url?: string|null, model?: string|null, temperature?: float|string|null, max_tokens?: int|string|null, api_key?: string|null, clear_key?: bool}  $data
     */
    public function save(array $data): void
    {
        $this->put('ai_enabled', ($data['enabled'] ?? true) ? '1' : '0');
        $this->put('ai_base_url', $this->nullable($data['base_url'] ?? null));
        $this->put('ai_model', $this->nullable($data['model'] ?? null));
        $this->put('ai_temperature', isset($data['temperature']) ? (string) $data['temperature'] : null);
        $this->put('ai_max_tokens', isset($data['max_tokens']) ? (string) $data['max_tokens'] : null);

        if (($data['clear_key'] ?? false) === true) {
            $this->put('ai_api_key', null);
        } elseif (isset($data['api_key']) && trim((string) $data['api_key']) !== '') {
            $this->put('ai_api_key', Crypt::encryptString(trim((string) $data['api_key'])));
        }
    }

    private function put(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->values = null;
    }

    private function nullable(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : trim((string) $value);
    }
}
