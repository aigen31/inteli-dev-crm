<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\Task\TaskCreated;
use App\Models\Task;
use App\Models\User;

class CreateTaskAction
{
    public function execute(array $data, User $creator): Task
    {
        $task = Task::create([
            'client_id' => $data['client_id'] ?? $creator->id,
            'created_by' => $creator->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'type' => $data['type'] ?? TaskType::OneTime,
            'status' => $creator->isClient()
                ? TaskStatus::PendingReview
                : ($data['status'] ?? TaskStatus::PendingReview),
            'priority' => $data['priority'] ?? TaskPriority::Medium,
            'deadline' => $data['deadline'] ?? null,
        ]);

        TaskCreated::dispatch($task);

        return $task;
    }
}
