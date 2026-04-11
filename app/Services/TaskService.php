<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

class TaskService
{
    public function getTasksQuery(?string $status = null, ?string $clientId = null): Builder
    {
        $query = Task::with(['client', 'creator']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return $query->latest();
    }

    public function getDashboardStats(): array
    {
        $counts = Task::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'pending_review' => $counts[TaskStatus::PendingReview->value] ?? 0,
            'queued' => $counts[TaskStatus::Queued->value] ?? 0,
            'in_progress' => $counts[TaskStatus::InProgress->value] ?? 0,
            'awaiting_review' => $counts[TaskStatus::AwaitingReview->value] ?? 0,
            'completed' => $counts[TaskStatus::Completed->value] ?? 0,
            'total' => $counts->sum(),
        ];
    }
}
