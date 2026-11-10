<?php

declare(strict_types=1);

namespace App\Http\Controllers\Executor;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $assigned = Task::query()->where('assignee_id', $user->id);

        $activeStatuses = [
            TaskStatus::Queued,
            TaskStatus::InProgress,
            TaskStatus::AwaitingReview,
            TaskStatus::RejectedByClient,
        ];

        $tasks = $assigned->clone()
            ->with(['client:id,name', 'project:id,key,name,color'])
            ->whereIn('status', $activeStatuses)
            ->orderByRaw('case when deadline is null then 1 else 0 end')
            ->orderBy('deadline')
            ->latest()
            ->limit(8)
            ->get();

        $reports = TaskReport::query()
            ->with(['task:id,title,assignee_id', 'recipient:id,name'])
            ->where('author_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Executor/Dashboard', [
            'tasks' => $tasks,
            'reports' => $reports,
            'counts' => [
                'active' => $assigned->clone()->whereIn('status', $activeStatuses)->count(),
                'in_progress' => $assigned->clone()->where('status', TaskStatus::InProgress)->count(),
                'awaiting_review' => $assigned->clone()->where('status', TaskStatus::AwaitingReview)->count(),
                'overdue' => $assigned->clone()
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<', today())
                    ->whereIn('status', $activeStatuses)
                    ->count(),
            ],
        ]);
    }
}
