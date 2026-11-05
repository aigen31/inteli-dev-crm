<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectMemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInvite extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'token_hash',
        'role',
        'email',
        'created_by',
        'expires_at',
        'used_at',
        'accepted_user_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectMemberRole::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        if ($this->used_at !== null) {
            return 'used';
        }

        return $this->expires_at->isPast() ? 'expired' : 'active';
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            'used' => 'использовано',
            'expired' => 'истекло',
            default => 'активно',
        };
    }
}
