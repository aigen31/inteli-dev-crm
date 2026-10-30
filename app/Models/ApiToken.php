<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApiAbility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'prefix',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }

    public function canRead(): bool
    {
        return $this->can(ApiAbility::Read->value) || $this->can(ApiAbility::Write->value);
    }

    public function canWrite(): bool
    {
        return $this->can(ApiAbility::Write->value);
    }
}
