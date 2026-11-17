<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\FormTemplate;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WidgetZoneController extends Controller
{
    private const WIDGETS = ['clients', 'active_tasks', 'revenue', 'focus', 'activity', 'deadlines', 'quick_links', 'activity_calendar', 'user_workload'];

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $activeStatuses = [
            TaskStatus::PendingReview,
            TaskStatus::Queued,
            TaskStatus::InProgress,
            TaskStatus::AwaitingReview,
            TaskStatus::RejectedByClient,
        ];

        $todayTasks = Task::with('client')
            ->when(! $isAdmin, fn ($q) => $q->where('assignee_id', $user->id))
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today())
            ->whereIn('status', $activeStatuses)
            ->orderBy('deadline')
            ->take(6)
            ->get();

        $pendingTasks = Task::with('client')
            ->when(! $isAdmin, fn ($q) => $q->where('assignee_id', $user->id))
            ->where('status', TaskStatus::PendingReview)
            ->latest()
            ->take(5)
            ->get();

        $upcomingDeadlines = Task::with('client')
            ->when(! $isAdmin, fn ($q) => $q->where('assignee_id', $user->id))
            ->whereNotNull('deadline')
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::RejectedByAdmin])
            ->where('deadline', '>=', today())
            ->orderBy('deadline')
            ->take(5)
            ->get();

        $recentHistory = TaskStatusHistory::query()
            ->with(['task:id,title,client_id,assignee_id', 'task.client:id,name', 'changedBy:id,name'])
            ->when(! $isAdmin, fn ($q) => $q->whereHas('task', fn ($task) => $task->where('assignee_id', $user->id)))
            ->latest('created_at')
            ->limit(6)
            ->get();

        $monthlyRevenue = (float) Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->when(! $isAdmin, fn ($q) => $q->where('client_id', $user->id))
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return response()->json([
            'isAdmin' => $isAdmin,
            'team' => $isAdmin ? User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'role' => $member->role->value,
                ])
                ->all() : [],
            'currentUserId' => $request->user()->id,
            'counters' => [
                'clients' => $isAdmin ? User::query()->where('role', '!=', 'admin')->where('is_active', true)->count() : 0,
                'active_tasks' => Task::query()
                    ->when(! $isAdmin, fn ($q) => $q->where('assignee_id', $user->id))
                    ->whereIn('status', $activeStatuses)
                    ->count(),
                'overdue' => Task::query()
                    ->when(! $isAdmin, fn ($q) => $q->where('assignee_id', $user->id))
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<', today())
                    ->whereIn('status', $activeStatuses)
                    ->count(),
                'forms' => FormTemplate::query()->where('is_active', true)->count(),
            ],
            'monthlyRevenue' => $monthlyRevenue,
            'focusTasks' => $todayTasks->isNotEmpty() ? $todayTasks : $pendingTasks,
            'upcomingDeadlines' => $upcomingDeadlines,
            'recentHistory' => $recentHistory,
        ]);
    }

    public function updateLayout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'widgets' => ['required', 'array', 'max:20'],
            'widgets.*.id' => ['required', 'string', 'distinct', Rule::in(self::WIDGETS)],
            'widgets.*.w' => ['required', 'integer', 'between:1,4'],
            'widgets.*.h' => ['required', 'integer', 'between:1,4'],
        ]);

        $request->user()->update(['widget_zone_layout' => $data['widgets']]);

        return response()->json(['widgets' => $data['widgets']]);
    }
}
