<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\CustomFieldEntity;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeTaskStatusRequest;
use App\Http\Requests\Admin\StoreTaskRequest;
use App\Http\Requests\Admin\UpdateTaskRequest;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\CustomFieldService;
use App\Services\HtmlSanitizer;
use App\Services\TaskTemplateService;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly TaskTemplateService $templates,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function index(Request $request): Response
    {
        $tasks = Task::query()
            ->with(['client:id,name', 'assignee:id,name', 'template:id,name'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->input('q'), fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status', 'client_id', 'type', 'q'),
            'statuses' => Options::taskStatuses(),
            'types' => Options::taskTypes(),
            'clients' => User::query()->where('role', UserRole::Client)->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'all' => Task::query()->count(),
                'active' => Task::query()->whereIn('status', [
                    TaskStatus::PendingReview,
                    TaskStatus::Queued,
                    TaskStatus::InProgress,
                    TaskStatus::AwaitingReview,
                    TaskStatus::RejectedByClient,
                ])->count(),
                'awaiting_review' => Task::query()->where('status', TaskStatus::AwaitingReview)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tasks/Create', [
            ...$this->formOptions(),
        ]);
    }

    public function store(
        StoreTaskRequest $request,
        CreateTaskAction $action,
    ): RedirectResponse {
        $data = $request->validated();
        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        if (! empty($data['task_template_id'])) {
            $template = TaskTemplate::query()->findOrFail($data['task_template_id']);
            $client = User::query()->findOrFail($data['client_id']);
            $task = $this->templates->apply($template, $client, $request->user(), [
                'title' => $data['title'],
                'description' => $data['description'],
                'deadline' => $data['deadline'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
            ]);
        } else {
            $task = $action->execute($data, $request->user());
        }

        $this->customFields->sync($task, $customValues, $request->user());

        return redirect()->route('admin.tasks.show', $task)
            ->with('success', 'Задача создана.');
    }

    public function show(Task $task): Response
    {
        $task->load([
            'client',
            'creator',
            'assignee',
            'template',
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
            'customFields' => $this->customFields->serializedValues($task),
        ]);
    }

    public function edit(Task $task): Response
    {
        return Inertia::render('Admin/Tasks/Edit', [
            'task' => $task->load(['assignee', 'template']),
            'customFields' => $this->customFields->serializedValues($task),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->sanitizer->sanitize($data['description']);
        }

        $task->update($data);
        $this->customFields->sync($task, $customValues, $request->user());

        return redirect()->route('admin.tasks.show', $task)
            ->with('success', 'Задача обновлена.');
    }

    public function updateStatus(ChangeTaskStatusRequest $request, Task $task, ChangeTaskStatusAction $action): RedirectResponse
    {
        $newStatus = TaskStatus::from($request->validated('status'));

        $action->execute($task, $newStatus, $request->user(), $request->validated('comment'));

        return back()->with('success', "Статус изменён на «{$newStatus->label()}».");
    }

    public function updateChecklist(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['required_with:checklist', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
        ]);

        $task->update(['checklist' => $data['checklist'] ?? []]);

        return back()->with('success', 'Чек-лист обновлён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'clients' => User::query()
                ->where('role', UserRole::Client)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'assignees' => User::query()
                ->where('role', UserRole::Admin)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'templates' => TaskTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'types' => Options::taskTypes(),
            'priorities' => Options::priorities(),
            'statuses' => Options::taskStatuses(),
            'fields' => $this->customFields->serializedDefinitions(CustomFieldEntity::Task),
        ];
    }
}
