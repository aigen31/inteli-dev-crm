<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaskReport;
use App\Models\User;

class TaskReportPolicy
{
    public function view(User $user, TaskReport $report): bool
    {
        if ($user->isAdmin() || $report->author_id === $user->id || $report->recipient_id === $user->id) {
            return true;
        }

        return $report->recipient_id === null
            && $report->task !== null
            && $report->task->client_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
