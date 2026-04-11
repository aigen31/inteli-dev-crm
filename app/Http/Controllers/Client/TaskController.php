<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReviewTaskRequest;
use App\Http\Requests\Client\StoreTaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $tasks = $request->user()
            ->clientTasks()
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Client/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status'),
            'statuses' => collect(TaskStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Client/Tasks/Create', [
            'priorities' => collect(TaskPriority::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTaskAction $action): RedirectResponse
    {
        $task = $action->execute($request->validated(), $request->user());

        return redirect()->route('client.tasks.show', $task)
            ->with('success', 'Заявка отправлена.');
    }

    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'comments.author',
            'reports.author',
            'invoices',
            'statusHistory.changedBy',
        ]);

        $canReview = $task->status === TaskStatus::AwaitingReview;

        return Inertia::render('Client/Tasks/Show', [
            'task' => $task,
            'canReview' => $canReview,
        ]);
    }

    public function review(ReviewTaskRequest $request, Task $task, ChangeTaskStatusAction $action): RedirectResponse
    {
        $this->authorize('changeStatus', $task);

        $newStatus = TaskStatus::from($request->validated('status'));

        $action->execute($task, $newStatus, $request->user(), $request->validated('comment'));

        return back()->with('success', $newStatus === TaskStatus::Completed
            ? 'Работа принята.'
            : 'Работа отклонена.');
    }
}
