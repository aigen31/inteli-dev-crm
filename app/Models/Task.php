<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Concerns\HasCustomFields;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasCustomFields, HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'created_by',
        'task_template_id',
        'form_template_id',
        'form_data',
        'assignee_id',
        'title',
        'description',
        'checklist',
        'type',
        'source',
        'status',
        'priority',
        'estimated_minutes',
        'deadline',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'source' => TaskSource::class,
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'checklist' => 'array',
            'form_data' => 'array',
            'estimated_minutes' => 'integer',
            'deadline' => 'date',
            'completed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TaskReport::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class);
    }

    public function isMaintenance(): bool
    {
        return $this->type === TaskType::Maintenance;
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
}
