<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskCommentPolicy
{
    public function viewAny(User $user, Task $task): bool
    {
        return $this->canAccess($user, $task);
    }

    public function create(User $user, Task $task): bool
    {
        if (! $task->status->canAddComments()) {
            return false;
        }

        return $this->canAccess($user, $task);
    }

    private function canAccess(User $user, Task $task): bool
    {
        return $user->isAdmin()
            || $task->client_id === $user->id
            || ($user->isExecutor() && $task->assignee_id === $user->id);
    }
}
