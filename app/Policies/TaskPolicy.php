<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($task->client_id !== $user->id) {
            return false;
        }

        return $task->project === null || $task->project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    public function changeStatus(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isClient() && $task->client_id === $user->id) {
            return in_array($task->status->value, ['awaiting_review'], true)
                && ($task->project === null || $task->project->isVisibleTo($user));
        }

        return false;
    }
}
