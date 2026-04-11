<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskReportRequest;
use App\Models\Task;
use App\Models\TaskReport;
use Illuminate\Http\RedirectResponse;

class TaskReportController extends Controller
{
    public function store(StoreTaskReportRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('create', [TaskReport::class, $task]);

        $task->reports()->create([
            'author_id' => $request->user()->id,
            'content' => $request->validated('content'),
            'attachments' => $request->validated('attachments'),
        ]);

        return back()->with('success', 'Отчёт добавлен.');
    }
}
