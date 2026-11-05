<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskReport extends Model
{
    /** @use HasFactory<TaskReportFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'task_id',
        'author_id',
        'recipient_id',
        'title',
        'content',
        'test_url',
        'screenshot_id',
        'attachments',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(FileAttachment::class, 'screenshot_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskReportItem::class)->orderBy('position')->orderBy('created_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }
}
