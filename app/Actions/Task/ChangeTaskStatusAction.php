<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Events\Task\TaskStatusChanged;
use App\Models\Task;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\Tasks\TaskStrictControl;
use Illuminate\Validation\ValidationException;

class ChangeTaskStatusAction
{
    public function __construct(
        private readonly TaskStrictControl $control,
        private readonly BalanceService $balance,
    ) {}

    public function execute(Task $task, TaskStatus $newStatus, User $changedBy, ?string $comment = null): Task
    {
        $oldStatus = $task->status;

        if ($oldStatus === $newStatus) {
            throw ValidationException::withMessages([
                'status' => 'Задача уже в этом статусе.',
            ]);
        }

        if (! $changedBy->isAdmin() && ! $oldStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Нельзя перевести задачу из «{$oldStatus->label()}» в «{$newStatus->label()}».",
            ]);
        }

        if (! $this->control->canChange($task, $changedBy, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'У вас нет прав для изменения статуса задачи.',
            ]);
        }

        $task->status = $newStatus;

        if ($newStatus === TaskStatus::Completed) {
            $task->completed_at = now();
        }

        $task->save();

        if ($newStatus === TaskStatus::Completed) {
            $this->balance->settleCompletedTask($task, $changedBy);
        }

        TaskStatusChanged::dispatch($task, $oldStatus, $newStatus, $changedBy, $comment);

        return $task;
    }
}
