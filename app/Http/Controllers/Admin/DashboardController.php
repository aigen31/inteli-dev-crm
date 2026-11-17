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
use App\Services\TaskService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, TaskService $taskService): Response
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
            'team' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                ])
                ->all(),
            'currentUserId' => $request->user()->id,
            'taskStats' => $taskStats,
            'pendingTasks' => $pendingTasks,
            'pendingInvoices' => $pendingInvoices,
            'monthlyRevenue' => $monthlyRevenue,
            'upcomingDeadlines' => $upcomingDeadlines,
            'todayTasks' => $todayTasks,
            'recentHistory' => $recentHistory,
            'counters' => [
                'clients' => User::query()->where('role', '!=', 'admin')->where('is_active', true)->count(),
                'active_tasks' => Task::query()->whereIn('status', $activeStatuses)->count(),
                'overdue' => Task::query()
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<', today())
                    ->whereIn('status', $activeStatuses)
                    ->count(),
                'forms' => FormTemplate::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
