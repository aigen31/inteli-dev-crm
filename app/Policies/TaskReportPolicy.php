<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;

class TaskReportPolicy
{
    public function view(User $user, TaskReport $report): bool
    {
        if ($user->isAdmin() || $report->author_id === $user->id || $report->recipient_id === $user->id) {
            return true;
        }

        if ($report->task?->assignee_id === $user->id) {
            return true;
        }

        return $report->recipient_id === null
            && $report->task !== null
            && $report->task->client_id === $user->id;
    }

    public function update(User $user, TaskReport $report): bool
    {
        return $user->isAdmin() || $report->author_id === $user->id;
    }

    public function delete(User $user, TaskReport $report): bool
    {
        return $user->isAdmin() || $report->author_id === $user->id;
    }

    public function create(User $user, ?Task $task = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $task instanceof Task) {
            return true;
        }

        return $task->assignee_id === $user->id || $task->client_id === $user->id;
    }
}
