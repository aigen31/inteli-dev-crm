<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\CustomFieldEntity;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateTasksRequest;
use App\Http\Requests\Admin\ChangeTaskStatusRequest;
use App\Http\Requests\Admin\StoreAdminFormTaskRequest;
use App\Http\Requests\Admin\StoreTaskRequest;
use App\Http\Requests\Admin\UpdateTaskRequest;
use App\Models\FormTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\CustomFieldService;
use App\Services\FormTemplateService;
use App\Services\HtmlSanitizer;
use App\Services\Tasks\TaskStrictControl;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly HtmlSanitizer $sanitizer,
        private readonly TaskStrictControl $strictControl,
    ) {}

    public function index(Request $request): Response
    {
        $tasks = Task::query()
            ->with(['client:id,name', 'assignee:id,name', 'project:id,key,name,color'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->input('project_id'), fn ($q, $projectId) => $q->where('project_id', $projectId))
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->input('q'), fn ($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $tasks->through(fn (Task $task): array => [
            ...$task->toArray(),
            'allowed_transitions' => $this->strictControl->allowed($task, $request->user()),
            'strict_control' => $task->strict_control,
            'strict_control_active' => $this->strictControl->enabledFor($task),
        ]);

        return Inertia::render('Admin/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status', 'client_id', 'type', 'project_id', 'q'),
            'statuses' => Options::taskStatuses(),
            'types' => Options::taskTypes(),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'priorities' => Options::priorities(),
            'clients' => User::query()->where('role', '!=', UserRole::Admin)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'key', 'name', 'color']),
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
            'forms' => FormTemplate::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (FormTemplate $form): array => $form->toLibraryArray())
                ->values(),
        ]);
    }

    public function createFromForm(): Response
    {
        return Inertia::render('Admin/Tasks/CreateFromForm', [
            ...$this->formOptions(),
            'forms' => FormTemplate::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (FormTemplate $form): array => $form->toLibraryArray())
                ->values(),
        ]);
    }

    public function storeForm(
        StoreAdminFormTaskRequest $request,
        FormTemplate $formTemplate,
        FormTemplateService $forms,
    ): RedirectResponse {
        $data = $request->validated();
        $client = User::query()->findOrFail($data['client_id']);

        $task = $forms->createTask($formTemplate, $client, [
            'title' => $data['title'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'priority' => $data['priority'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'form_data' => $data['form_data'],
        ], $request->user(), TaskSource::Manual);

        $task->update(array_filter([
            'project_id' => $data['project_id'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'budget' => $data['budget'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        return redirect()->route('admin.tasks.show', $task)
            ->with('success', 'Задача создана из формы.');
    }

    public function store(
        StoreTaskRequest $request,
        CreateTaskAction $action,
        ConsentService $consents,
    ): RedirectResponse {
        $data = $request->validated();
        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        if (! empty($data['project_id'])) {
            $this->authorize('createTask', Project::query()->findOrFail($data['project_id']));
        }

        $task = $action->execute($data, $request->user());

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
            'formTemplate',
            'comments.author',
            'reports.author:id,name',
            'reports.recipient:id,name',
            'reports.screenshot',
            'reports.items.screenshot',
            'invoices',
            'statusHistory.changedBy',
            'project',
        ]);

        $allowedTransitions = collect($this->strictControl->allowed($task, request()->user()));

        return Inertia::render('Admin/Tasks/Show', [
            'task' => $task,
            'allowedTransitions' => $allowedTransitions,
            'strictControl' => [
                'override' => $task->strict_control,
                'active' => $this->strictControl->enabledFor($task),
            ],
            'customFields' => $this->customFields->serializedValues($task),
        ]);
    }

    public function edit(Task $task): Response
    {
        return Inertia::render('Admin/Tasks/Edit', [
            'task' => $task->load(['assignee', 'formTemplate']),
            'customFields' => $this->customFields->serializedValues($task),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        // Структурированные ответы шаблона админ правит как и клиент.
        if (array_key_exists('form_data', $data)) {
            $data['form_data'] = $task->form_template_id === null ? $task->form_data : $data['form_data'];
        }

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

    public function bulkUpdate(BulkUpdateTasksRequest $request, ChangeTaskStatusAction $action): RedirectResponse
    {
        $data = $request->validated();
        $ids = $data['task_ids'];
        $status = isset($data['status']) ? TaskStatus::from($data['status']) : null;
        $comment = $data['comment'] ?? null;
        $fields = Arr::except($data, ['task_ids', 'status', 'comment']);

        $tasks = Task::query()->whereIn('id', $ids)->get();
        $updated = 0;
        $statusChanged = 0;
        $statusSkipped = 0;

        foreach ($tasks as $task) {
            $task->fill($fields);
            if ($task->isDirty()) {
                $task->save();
            }

            if ($status !== null) {
                if ($this->strictControl->canChange($task, $request->user(), $status)) {
                    $action->execute($task, $status, $request->user(), $comment);
                    $statusChanged++;
                } else {
                    $statusSkipped++;
                }
            }

            $updated++;
        }

        if ($updated === 0) {
            return back()->with('error', 'Не удалось обновить выбранные задачи.');
        }

        $message = "Обновлено задач: {$updated}.";

        if ($status !== null) {
            $message .= " Статус изменён: {$statusChanged}.";
        }

        if ($statusSkipped > 0) {
            $message .= " Пропущено по статусу: {$statusSkipped}.";
        }

        return back()->with('success', $message);
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
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
            'assignees' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'types' => Options::taskTypes(),
            'priorities' => Options::priorities(),
            'statuses' => Options::taskStatuses(),
            'fields' => $this->customFields->serializedDefinitions(CustomFieldEntity::Task),
            'projects' => Project::query()->whereNull('archived_at')->with(['members:id,project_id,user_id,delegation'])->orderBy('name')->get(['id', 'key', 'name', 'color', 'client_id', 'type']),
        ];
    }
}
