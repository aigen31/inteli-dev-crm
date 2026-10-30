<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Events\Task\TaskStatusChanged;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ChangeTaskStatusAction
{
    public function execute(Task $task, TaskStatus $newStatus, User $changedBy, ?string $comment = null): Task
    {
        $oldStatus = $task->status;

        if (! $oldStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Нельзя перевести задачу из «{$oldStatus->label()}» в «{$newStatus->label()}».",
            ]);
        }

        if (! $oldStatus->canBeChangedBy($changedBy->role, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'У вас нет прав для изменения статуса задачи.',
            ]);
        }

        $task->status = $newStatus;

        if ($newStatus === TaskStatus::Completed) {
            $task->completed_at = now();
        }

        $task->save();

        TaskStatusChanged::dispatch($task, $oldStatus, $newStatus, $changedBy, $comment);

        return $task;
    }
}
