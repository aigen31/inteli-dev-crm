<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\TaskTemplate;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, CustomFieldService $customFields): Response
    {
        $user = $request->user();

        $taskStats = $user->clientTasks()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $awaitingReview = $user->clientTasks()
            ->where('status', TaskStatus::AwaitingReview)
            ->latest()
            ->take(5)
            ->get(['id', 'title', 'status', 'updated_at']);

        $pendingInvoices = $user->invoices()
            ->where('status', InvoiceStatus::Pending)
            ->with('task:id,title')
            ->latest()
            ->take(5)
            ->get();

        $upcomingTasks = $user->clientTasks()
            ->whereIn('status', [
                TaskStatus::PendingReview,
                TaskStatus::Queued,
                TaskStatus::InProgress,
                TaskStatus::AwaitingReview,
            ])
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->latest()
            ->take(5)
            ->get(['id', 'title', 'status', 'deadline']);

        return Inertia::render('Client/Dashboard', [
            'taskStats' => $taskStats,
            'awaitingReview' => $awaitingReview,
            'pendingInvoices' => $pendingInvoices,
            'upcomingTasks' => $upcomingTasks,
            'templates' => TaskTemplate::query()
                ->visibleTo($user)
                ->orderByDesc('usage_count')
                ->take(4)
                ->get(['id', 'name', 'summary', 'estimated_minutes', 'default_due_days']),
            'customFields' => $customFields->serializedValues($user),
        ]);
    }
}
