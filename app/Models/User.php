<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectDelegation;
use App\Enums\UserRole;
use App\Models\Concerns\HasCustomFields;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasCustomFields, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'role',
        'name',
        'email',
        'phone',
        'messenger_contacts',
        'password',
        'is_active',
        'balance',
        'locale',
        'theme',
        'features',
        'sidebar_preferences',
        'dashboard_layout',
        'widget_zone_layout',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'messenger_contacts' => 'array',
            'password' => 'hashed',
            'features' => 'array',
            'sidebar_preferences' => 'array',
            'dashboard_layout' => 'array',
            'widget_zone_layout' => 'array',
            'is_active' => 'boolean',
            'balance' => 'decimal:2',
        ];
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) data_get($this->features ?? [], $feature, false);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Роли «клиент» и «исполнитель» объединены: любой не-админ может быть
     * назначен и заказчиком, и исполнителем в рамках проекта или задачи.
     */
    public function isClient(): bool
    {
        return ! $this->isAdmin();
    }

    public function isExecutor(): bool
    {
        return ! $this->isAdmin();
    }

    public function isUser(): bool
    {
        return ! $this->isAdmin();
    }

    /**
     * Роли для фильтрации инструментов: обычный пользователь считается и
     * клиентом, и исполнителем — кем он выступает, решает назначение.
     *
     * @return list<string>
     */
    public function roleNames(): array
    {
        if ($this->isAdmin()) {
            return [UserRole::Admin->value];
        }

        return [UserRole::User->value, UserRole::Client->value, UserRole::Executor->value];
    }

    public function hasClientDelegation(): bool
    {
        return $this->isClient()
            || $this->clientTasks()->exists()
            || $this->clientProjects()->exists()
            || $this->projectMemberships()->where('delegation', ProjectDelegation::Client->value)->exists();
    }

    public function hasExecutorDelegation(): bool
    {
        return $this->isExecutor()
            || $this->assignedTasks()->exists()
            || $this->projectMemberships()->where('delegation', ProjectDelegation::Executor->value)->exists();
    }

    public function roleForTask(Task $task): UserRole
    {
        if ($this->isAdmin()) {
            return UserRole::Admin;
        }

        if ($task->assignee_id === $this->id) {
            return UserRole::Executor;
        }

        if ($task->client_id === $this->id) {
            return UserRole::Client;
        }

        return $this->role;
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function clientTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'client_id');
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function clientProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function memberProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role', 'added_by'])
            ->withTimestamps();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'author_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    public function fileAttachments(): HasMany
    {
        return $this->hasMany(FileAttachment::class, 'uploaded_by');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class, 'created_by');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }
}
