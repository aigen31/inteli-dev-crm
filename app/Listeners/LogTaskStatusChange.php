<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Task\TaskStatusChanged;
use App\Models\TaskStatusHistory;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogTaskStatusChange implements ShouldQueue
{
    public function handle(TaskStatusChanged $event): void
    {
        TaskStatusHistory::create([
            'task_id' => $event->task->id,
            'changed_by' => $event->changedBy->id,
            'old_status' => $event->oldStatus,
            'new_status' => $event->newStatus,
            'comment' => $event->comment,
        ]);
    }
}
