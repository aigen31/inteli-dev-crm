<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskReportPolicy
{
    public function viewAny(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->client_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
