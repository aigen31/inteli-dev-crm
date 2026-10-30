<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class ApiTokenService
{
    /**
     * @param  list<string>  $abilities
     * @return array{token: ApiToken, plain: string}
     */
    public function create(User $user, string $name, array $abilities, ?int $expiresInDays = null): array
    {
        $plain = 'crm_'.Str::random(48);

        $token = $user->apiTokens()->create([
            'name' => $name,
            'prefix' => substr($plain, 0, 12),
            'token_hash' => $this->hash($plain),
            'abilities' => array_values(array_unique($abilities)),
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    public function findByPlainText(string $plain): ?ApiToken
    {
        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', $this->hash($plain))
            ->first();

        if (! $token instanceof ApiToken || $token->isExpired()) {
            return null;
        }

        if ($token->user === null || ! $token->user->is_active) {
            return null;
        }

        return $token;
    }

    public function touch(ApiToken $token): void
    {
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }
    }

    public function revoke(ApiToken $token): void
    {
        $token->delete();
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
