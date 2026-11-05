<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReportItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'task_report_id',
        'position',
        'content',
        'test_url',
        'screenshot_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(TaskReport::class, 'task_report_id');
    }

    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(FileAttachment::class, 'screenshot_id');
    }
}
