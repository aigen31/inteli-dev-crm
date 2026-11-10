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

        if ($task->assignee_id === $user->id) {
            return true;
        }

        if ($task->client_id !== $user->id) {
            return false;
        }

        return $task->project === null || $task->project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasClientDelegation();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    public function updateChecklist(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->assignee_id === $user->id;
    }

    public function changeStatus(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($task->assignee_id === $user->id) {
            return true;
        }

        if ($task->client_id === $user->id) {
            return $task->status->value === 'awaiting_review'
                && ($task->project === null || $task->project->isVisibleTo($user));
        }

        return false;
    }
}
