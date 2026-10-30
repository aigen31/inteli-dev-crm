<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskTemplate extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'summary',
        'description',
        'checklist',
        'default_type',
        'default_priority',
        'default_status',
        'default_assignee_id',
        'default_due_days',
        'estimated_minutes',
        'client_id',
        'is_active',
        'is_shared',
        'usage_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'default_type' => TaskType::class,
            'default_priority' => TaskPriority::class,
            'default_status' => TaskStatus::class,
            'default_due_days' => 'integer',
            'estimated_minutes' => 'integer',
            'is_active' => 'boolean',
            'is_shared' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return list<array{text: string, done: bool}>
     */
    public function normalizedChecklist(): array
    {
        $items = [];

        foreach ($this->checklist ?? [] as $item) {
            if (is_string($item)) {
                $items[] = ['text' => $item, 'done' => false];

                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));

            if ($text !== '') {
                $items[] = ['text' => $text, 'done' => (bool) ($item['done'] ?? false)];
            }
        }

        return $items;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('is_active', true);

        if ($user->isClient()) {
            $query->where('is_shared', true)
                ->where(fn (Builder $q) => $q->whereNull('client_id')->orWhere('client_id', $user->id));
        }

        return $query;
    }
}
