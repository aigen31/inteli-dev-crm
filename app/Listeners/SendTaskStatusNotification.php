<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\TaskStatus;
use App\Events\Task\TaskStatusChanged;
use App\Models\User;
use App\Notifications\TaskAcceptedByClient;
use App\Notifications\TaskRejectedByClient;
use App\Notifications\TaskReviewRequired;
use App\Notifications\TaskStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskStatusNotification implements ShouldQueue
{
    public function handle(TaskStatusChanged $event): void
    {
        $task = $event->task;
        $client = $task->client;
        $admin = User::where('role', 'admin')->first();

        match ($event->newStatus) {
            TaskStatus::AwaitingReview => $client->notify(new TaskReviewRequired($task)),
            TaskStatus::Completed => $admin?->notify(new TaskAcceptedByClient($task)),
            TaskStatus::RejectedByClient => $admin?->notify(new TaskRejectedByClient($task, $event->comment)),
            default => $client->notify(new TaskStatusUpdated($task, $event->newStatus)),
        };
    }
}
