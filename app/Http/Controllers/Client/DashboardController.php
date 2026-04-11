<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
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

        return Inertia::render('Client/Dashboard', [
            'taskStats' => $taskStats,
            'awaitingReview' => $awaitingReview,
            'pendingInvoices' => $pendingInvoices,
        ]);
    }
}
