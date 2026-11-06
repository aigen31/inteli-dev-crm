<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class SecretFeatureService
{
    public const XP_THEME = 'xp_theme';

    /** @var list<string> */
    public const FEATURES = [self::XP_THEME];

    /**
     * @return array<string, mixed>|null
     */
    public function unlock(User $user, string $code): ?string
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        foreach (self::FEATURES as $feature) {
            $expected = (string) config("features.secret_codes.{$feature}", '');

            if ($expected === '' || ! hash_equals(hash('sha256', $expected), hash('sha256', $code))) {
                continue;
            }

            $features = is_array($user->features) ? $user->features : [];
            $features[$feature] = true;
            $user->update(['features' => $features]);

            return $feature;
        }

        return null;
    }

    public function has(User $user, string $feature): bool
    {
        return (bool) data_get($user->features ?? [], $feature, false);
    }
}
