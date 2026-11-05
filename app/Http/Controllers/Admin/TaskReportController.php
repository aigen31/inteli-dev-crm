<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskReportRequest;
use App\Models\Task;
use App\Models\TaskReport;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;

class TaskReportController extends Controller
{
    public function store(StoreTaskReportRequest $request, Task $task, ReportService $reports): RedirectResponse
    {
        $this->authorize('create', [TaskReport::class, $task]);

        $data = $request->validated();

        $reports->create($request->user(), [
            'task_id' => $task->id,
            'recipient_id' => $data['recipient_id'] ?? $task->client_id,
            'title' => $data['title'] ?? null,
            'items' => [[
                'summary' => $data['content'],
                'test_url' => $data['test_url'] ?? null,
                'screenshot' => $data['screenshot'] ?? null,
            ]],
        ]);

        return back()->with('success', 'Отчёт добавлен.');
    }
}
