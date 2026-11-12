<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
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
    private const WIDGETS = ['clients', 'active_tasks', 'revenue', 'templates', 'focus', 'activity', 'deadlines', 'quick_links', 'activity_calendar'];

    public function data(): JsonResponse
    {
        $activeStatuses = [
            TaskStatus::PendingReview,
            TaskStatus::Queued,
            TaskStatus::InProgress,
            TaskStatus::AwaitingReview,
            TaskStatus::RejectedByClient,
        ];

        $todayTasks = Task::with('client')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today())
            ->whereIn('status', $activeStatuses)
            ->orderBy('deadline')
            ->take(6)
            ->get();

        $pendingTasks = Task::with('client')
            ->where('status', TaskStatus::PendingReview)
            ->latest()
            ->take(5)
            ->get();

        $upcomingDeadlines = Task::with('client')
            ->whereNotNull('deadline')
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::RejectedByAdmin])
            ->where('deadline', '>=', today())
            ->orderBy('deadline')
            ->take(5)
            ->get();

        $recentHistory = TaskStatusHistory::query()
            ->with(['task:id,title,client_id', 'task.client:id,name', 'changedBy:id,name'])
            ->latest('created_at')
            ->limit(6)
            ->get();

        $monthlyRevenue = (float) Invoice::where('status', InvoiceStatus::Paid)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return response()->json([
            'counters' => [
                'clients' => User::query()->where('role', 'client')->where('is_active', true)->count(),
                'active_tasks' => Task::query()->whereIn('status', $activeStatuses)->count(),
                'overdue' => Task::query()
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
