<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\FormTemplate;
use App\Models\Task;
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

        $taskStats = Task::query()->visibleTo($user)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $awaitingReview = Task::query()->visibleTo($user)
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

        $upcomingTasks = Task::query()->visibleTo($user)
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
            'forms' => FormTemplate::query()
                ->active()
                ->orderBy('sort_order')
                ->take(4)
                ->get()
                ->map(fn (FormTemplate $form) => $form->toLibraryArray())
                ->values(),
            'scenariosCount' => TaskTemplate::query()->visibleTo($user)->count(),
            'customFields' => $customFields->serializedValues($user),
        ]);
    }
}
