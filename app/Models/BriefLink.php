<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Одноразовая ссылка на заполнение брифа без аккаунта.
 *
 * Токен живёт только в URL: в базе хранится SHA-256, поэтому по дампу базы
 * ссылку не восстановить. Ссылка привязана к одному брифу, истекает,
 * отзывается и гасится после отправки.
 */
class BriefLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'brief_id',
        'created_by',
        'token_hash',
        'expires_at',
        'used_at',
        'revoked_at',
        'last_used_at',
        'last_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array{0: self, 1: string} ссылка и открытый токен для URL
     */
    public static function issue(Brief $brief, ?User $admin, int $days = 7): array
    {
        $token = Str::random(48);

        $link = self::create([
            'brief_id' => $brief->id,
            'created_by' => $admin?->id,
            'token_hash' => self::hash($token),
            'expires_at' => now()->addDays($days),
        ]);

        return [$link, $token];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findActive(string $token): ?self
    {
        if (strlen($token) < 32 || strlen($token) > 96) {
            return null;
        }

        return self::query()
            ->where('token_hash', self::hash($token))
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isActive(): bool
    {
        return $this->used_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function touchUsage(?string $ip): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'last_ip' => $ip,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'is_active' => $this->isActive(),
        ];
    }
}
