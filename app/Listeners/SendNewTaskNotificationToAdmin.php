<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Task\TaskCreated;
use App\Models\User;
use App\Notifications\NewTaskFromClient;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNewTaskNotificationToAdmin implements ShouldQueue
{
    public function handle(TaskCreated $event): void
    {
        $task = $event->task;

        if ($task->creator->isClient()) {
            $admin = User::where('role', 'admin')->first();
            $admin?->notify(new NewTaskFromClient($task));
        }
    }
}
