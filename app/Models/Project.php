<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'client_id',
        'status',
        'priority',
        'color',
        'site_url',
        'agents_md',
        'instructions',
        'studied_at',
        'starts_at',
        'due_at',
        'archived_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => TaskPriority::class,
            'studied_at' => 'datetime',
            'starts_at' => 'date',
            'due_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function studies(): HasMany
    {
        return $this->hasMany(ProjectStudy::class)->latest();
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(ProjectInvite::class)->latest();
    }

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'added_by'])
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AiDocument::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function taskReference(int $number): string
    {
        return $this->key.'-'.$number;
    }

    /**
     * Projects a user may see: admins see everything, clients see their own
     * projects and projects they were explicitly invited to.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('client_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin() || $this->client_id === $user->id) {
            return true;
        }

        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function memberRole(User $user): ?ProjectMemberRole
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->first()
            ?->role;
    }

    public function accessLabel(User $user): ?string
    {
        if ($user->isAdmin()) {
            return 'администратор';
        }

        if ($this->client_id === $user->id) {
            return 'владелец';
        }

        return $this->memberRole($user)?->label();
    }

    public function canCreateTasks(User $user): bool
    {
        if ($user->isAdmin() || $this->client_id === $user->id) {
            return true;
        }

        return $this->memberRole($user)?->canCreateTasks() ?? false;
    }

    public function visibleTasksFor(User $user): HasMany
    {
        return $this->tasks()
            ->when(
                $user->isClient(),
                fn (Builder $query) => $query->where('client_id', $user->id),
            );
    }
}
