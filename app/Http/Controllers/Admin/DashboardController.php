<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\TaskService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(TaskService $taskService): Response
    {
        $taskStats = $taskService->getDashboardStats();

        $activeStatuses = [
            TaskStatus::PendingReview,
            TaskStatus::Queued,
            TaskStatus::InProgress,
            TaskStatus::AwaitingReview,
            TaskStatus::RejectedByClient,
        ];

        $pendingTasks = Task::with('client')
            ->where('status', TaskStatus::PendingReview)
            ->latest()
            ->take(5)
            ->get();

        $pendingInvoices = Invoice::with(['client', 'task'])
            ->where('status', InvoiceStatus::Pending)
            ->latest()
            ->take(5)
            ->get();

        $monthlyRevenue = (float) Invoice::where('status', InvoiceStatus::Paid)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $upcomingDeadlines = Task::with('client')
            ->whereNotNull('deadline')
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::RejectedByAdmin])
            ->where('deadline', '>=', today())
            ->orderBy('deadline')
            ->take(5)
            ->get();

        $todayTasks = Task::with('client')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today())
            ->whereIn('status', $activeStatuses)
            ->orderBy('deadline')
            ->take(6)
            ->get();

        $recentHistory = TaskStatusHistory::query()
            ->with(['task:id,title,client_id', 'task.client:id,name', 'changedBy:id,name'])
            ->latest('created_at')
            ->limit(6)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'taskStats' => $taskStats,
            'pendingTasks' => $pendingTasks,
            'pendingInvoices' => $pendingInvoices,
            'monthlyRevenue' => $monthlyRevenue,
            'upcomingDeadlines' => $upcomingDeadlines,
            'todayTasks' => $todayTasks,
            'recentHistory' => $recentHistory,
            'counters' => [
                'clients' => User::query()->where('role', 'client')->where('is_active', true)->count(),
                'active_tasks' => Task::query()->whereIn('status', $activeStatuses)->count(),
                'overdue' => Task::query()
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<', today())
                    ->whereIn('status', $activeStatuses)
                    ->count(),
                'templates' => TaskTemplate::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
