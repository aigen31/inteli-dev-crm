<?php

declare(strict_types=1);

namespace App\Http\Controllers\Executor;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Executor\ChangeExecutorTaskStatusRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskStrictControl;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskStrictControl $strictControl,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tasks = Task::query()
            ->where('assignee_id', $user->id)
            ->with(['client:id,name', 'project:id,key,name,color'])
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Task $task): array => [
                ...$task->toArray(),
                'allowed_transitions' => $this->transitions($task, $request->user()),
            ]);

        return Inertia::render('Executor/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status'),
            'statuses' => Options::taskStatuses(),
            'counts' => [
                'all' => Task::query()->where('assignee_id', $user->id)->count(),
                'in_progress' => Task::query()->where('assignee_id', $user->id)->where('status', TaskStatus::InProgress)->count(),
                'awaiting_review' => Task::query()->where('assignee_id', $user->id)->where('status', TaskStatus::AwaitingReview)->count(),
            ],
        ]);
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'client:id,name',
            'project:id,key,name,color',
            'comments.author:id,name',
            'reports.author:id,name',
            'reports.items.screenshot',
            'statusHistory.changedBy:id,name',
        ]);

        return Inertia::render('Executor/Tasks/Show', [
            'task' => $task,
            'allowedTransitions' => $this->transitions($task, request()->user()),
            'strictControl' => [
                'override' => $task->strict_control,
                'active' => $this->strictControl->enabledFor($task),
            ],
        ]);
    }

    public function updateStatus(ChangeExecutorTaskStatusRequest $request, Task $task, ChangeTaskStatusAction $action): RedirectResponse
    {
        $this->authorize('changeStatus', $task);

        $newStatus = TaskStatus::from($request->validated('status'));
        $action->execute($task, $newStatus, $request->user(), $request->validated('comment'));

        return back()->with('success', "Статус изменён на «{$newStatus->label()}».");
    }

    public function updateChecklist(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('updateChecklist', $task);

        $data = $request->validate([
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['required_with:checklist', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
        ]);

        $task->update(['checklist' => $data['checklist'] ?? []]);

        return back()->with('success', 'Чек-лист обновлён.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function transitions(Task $task, User $user): array
    {
        return $this->strictControl->allowed($task, $user);
    }
}
