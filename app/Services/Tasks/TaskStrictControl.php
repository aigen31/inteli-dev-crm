<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use App\Services\SettingsService;

/**
 * Правило «кто может двигать статус задачи».
 *
 * Глобальный переключатель «строгий контроль задач» (по умолчанию включён)
 * означает штатную приёмку: «Ожидает оценки» → «Завершено» ставит только клиент.
 * Выключенный режим разрешает исполнителю закрыть задачу самому. Задача может
 * переопределить режим своим `strict_control` (null — наследовать).
 *
 * Админ вне этого правила: он может перевести задачу в любой статус.
 */
class TaskStrictControl
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function enabledFor(Task $task): bool
    {
        return $task->strict_control ?? $this->settings->strictTaskControl();
    }

    public function canChange(Task $task, User $user, TaskStatus $to): bool
    {
        if ($user->isAdmin()) {
            return $to !== $task->status;
        }

        if (! $task->status->canTransitionTo($to)) {
            return false;
        }

        $role = $user->roleForTask($task);

        if ($task->status->canBeChangedBy($role, $to)) {
            return true;
        }

        return ! $this->enabledFor($task)
            && $role === UserRole::Executor
            && $task->status === TaskStatus::AwaitingReview
            && $to === TaskStatus::Completed;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function allowed(Task $task, User $user): array
    {
        return collect(TaskStatus::cases())
            ->filter(fn (TaskStatus $status): bool => $this->canChange($task, $user, $status))
            ->map(fn (TaskStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();
    }
}
