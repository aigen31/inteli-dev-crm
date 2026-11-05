<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConsentLog;
use App\Models\User;
use Illuminate\Http\Request;

class ConsentService
{
    public function record(?User $user, string $form, Request $request, array $meta = [], string $document = 'consent'): ConsentLog
    {
        return ConsentLog::create([
            'user_id' => $user?->id,
            'form' => $form,
            'document' => $document,
            'version' => ConsentLog::VERSION,
            'accepted_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'meta' => $meta ?: null,
        ]);
    }

    public function has(User $user, string $form = 'client-account'): bool
    {
        return ConsentLog::query()
            ->where('user_id', $user->id)
            ->where('form', $form)
            ->exists();
    }

    public function hasAny(User $user): bool
    {
        return ConsentLog::query()->where('user_id', $user->id)->exists();
    }
}
