<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Task\CloneMaintenanceTaskAction;
use App\Enums\TaskStatus;
use App\Events\Task\TaskStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

class CloneMaintenanceTaskOnComplete implements ShouldQueue
{
    public function __construct(
        private readonly CloneMaintenanceTaskAction $cloneAction,
    ) {}

    public function handle(TaskStatusChanged $event): void
    {
        if ($event->newStatus !== TaskStatus::Completed) {
            return;
        }

        if (! $event->task->isMaintenance()) {
            return;
        }

        $this->cloneAction->execute($event->task);
    }
}
