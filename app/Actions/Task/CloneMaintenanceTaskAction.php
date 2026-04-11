<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Models\Task;

class CloneMaintenanceTaskAction
{
    public function execute(Task $originalTask): Task
    {
        return Task::create([
            'client_id' => $originalTask->client_id,
            'created_by' => $originalTask->created_by,
            'title' => $originalTask->title,
            'description' => $originalTask->description,
            'type' => $originalTask->type,
            'status' => TaskStatus::Queued,
            'priority' => $originalTask->priority,
            'deadline' => null,
        ]);
    }
}
