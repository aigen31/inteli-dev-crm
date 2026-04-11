<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeTaskStatusRequest;
use App\Http\Requests\Admin\StoreTaskRequest;
use App\Http\Requests\Admin\UpdateTaskRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $tasks = Task::with(['client', 'creator'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status', 'client_id', 'type'),
            'statuses' => collect(TaskStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'types' => collect(TaskType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function create(): Response
    {
        $clients = User::where('role', UserRole::Client)
            ->where('is_active', true)
            ->get(['id', 'name', 'email']);

        return Inertia::render('Admin/Tasks/Create', [
            'clients' => $clients,
            'types' => collect(TaskType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'priorities' => collect(TaskPriority::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTaskAction $action): RedirectResponse
    {
        $task = $action->execute($request->validated(), $request->user());

        return redirect()->route('admin.tasks.show', $task)
            ->with('success', 'Задача создана.');
    }

    public function show(Task $task): Response
    {
        $task->load([
            'client',
            'creator',
            'comments.author',
            'reports.author',
            'invoices',
            'statusHistory.changedBy',
        ]);

        $allowedTransitions = collect($task->status->allowedTransitions())
            ->filter(fn (TaskStatus $s) => $task->status->canBeChangedBy(UserRole::Admin, $s))
            ->map(fn (TaskStatus $s) => ['value' => $s->value, 'label' => $s->label()])
            ->values();

        return Inertia::render('Admin/Tasks/Show', [
            'task' => $task,
            'allowedTransitions' => $allowedTransitions,
        ]);
    }

    public function edit(Task $task): Response
    {
        $clients = User::where('role', UserRole::Client)
            ->where('is_active', true)
            ->get(['id', 'name', 'email']);

        return Inertia::render('Admin/Tasks/Edit', [
            'task' => $task,
            'clients' => $clients,
            'types' => collect(TaskType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'priorities' => collect(TaskPriority::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return redirect()->route('admin.tasks.show', $task)
            ->with('success', 'Задача обновлена.');
    }

    public function updateStatus(ChangeTaskStatusRequest $request, Task $task, ChangeTaskStatusAction $action): RedirectResponse
    {
        $newStatus = TaskStatus::from($request->validated('status'));

        $action->execute($task, $newStatus, $request->user(), $request->validated('comment'));

        return back()->with('success', "Статус изменён на «{$newStatus->label()}».");
    }
}
