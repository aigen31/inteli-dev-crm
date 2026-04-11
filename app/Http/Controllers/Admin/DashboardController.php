<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Task;
use App\Services\TaskService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(TaskService $taskService): Response
    {
        $taskStats = $taskService->getDashboardStats();

        $pendingTasks = Task::with('client')
            ->where('status', 'pending_review')
            ->latest()
            ->take(5)
            ->get();

        $pendingInvoices = Invoice::with(['client', 'task'])
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $monthlyRevenue = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $upcomingDeadlines = Task::with('client')
            ->whereNotNull('deadline')
            ->whereNotIn('status', ['completed', 'rejected_by_admin'])
            ->where('deadline', '>=', today())
            ->orderBy('deadline')
            ->take(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'taskStats' => $taskStats,
            'pendingTasks' => $pendingTasks,
            'pendingInvoices' => $pendingInvoices,
            'monthlyRevenue' => (float) $monthlyRevenue,
            'upcomingDeadlines' => $upcomingDeadlines,
        ]);
    }
}
