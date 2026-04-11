<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function viewAny(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->client_id === $user->id;
    }

    public function create(User $user, Task $task): bool
    {
        if (! $task->status->canAddComments()) {
            return false;
        }

        return $user->isAdmin() || $task->client_id === $user->id;
    }
}
