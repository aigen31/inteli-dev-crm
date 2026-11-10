<?php

declare(strict_types=1);

namespace App\Http\Controllers\Executor;

use App\Http\Controllers\Controller;
use App\Models\TaskReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = TaskReport::query()
            ->with(['task:id,title,assignee_id', 'recipient:id,name'])
            ->where('author_id', $request->user()->id)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TaskReport $report): array => [
                'id' => $report->id,
                'title' => $report->title,
                'content' => $report->content,
                'task' => $report->task ? ['id' => $report->task->id, 'title' => $report->task->title] : null,
                'recipient' => $report->recipient?->name,
                'created_at' => $report->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Executor/Reports/Index', ['reports' => $reports]);
    }
}
