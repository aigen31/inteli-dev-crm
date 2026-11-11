<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\TaskStatus;
use App\Events\Task\TaskStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReviewTaskRequest;
use App\Http\Requests\Client\StoreTaskRequest;
use App\Models\FormTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Services\ConsentService;
use App\Services\CustomFieldService;
use App\Services\FormTemplateService;
use App\Services\HtmlSanitizer;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tasks = Task::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'project:id,key,name,color'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Client/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status'),
            'statuses' => Options::taskStatuses(),
            'counts' => [
                'all' => Task::query()->visibleTo($user)->count(),
                'active' => Task::query()->visibleTo($user)->whereIn('status', [
                    TaskStatus::PendingReview,
                    TaskStatus::Queued,
                    TaskStatus::InProgress,
                    TaskStatus::AwaitingReview,
                    TaskStatus::RejectedByClient,
                ])->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $forms = FormTemplate::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (FormTemplate $form) => $form->toLibraryArray())
            ->values();

        $user = request()->user();

        return Inertia::render('Client/Tasks/Create', [
            'projects' => Project::query()
                ->visibleTo($user)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get()
                ->filter(fn (Project $project): bool => $project->canCreateTasks($user))
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'client_id' => $project->client_id,
                ])
                ->values(),
            'priorities' => Options::priorities(),
            'forms' => $forms,
        ]);
    }

    public function storeForm(
        Request $request,
        FormTemplate $formTemplate,
        FormTemplateService $forms,
        ConsentService $consents,
    ): RedirectResponse {
        $data = $request->validate([
            'consent' => ['accepted'],
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
        ]);

        if (! empty($data['project_id'])) {
            $project = Project::query()->visibleTo($request->user())->findOrFail($data['project_id']);
            $this->authorize('createTask', $project);
        }

        $task = $forms->createTask(
            $formTemplate,
            $request->user(),
            [
                'title' => $request->input('title'),
                'project_id' => $data['project_id'] ?? null,
                'priority' => $request->input('priority'),
                'deadline' => $request->input('deadline') ?: null,
                'form_data' => $request->input('form_data', []),
            ],
            $request->user(),
        );

        $consents->record($request->user(), 'client-task-form', $request, ['task_id' => $task->id]);

        return redirect()
            ->route('client.tasks.show', $task)
            ->with('success', 'Заявка собрана из формы и отправлена.');
    }

    public function store(StoreTaskRequest $request, CreateTaskAction $action, ConsentService $consents): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        if (! empty($data['project_id'])) {
            $project = Project::query()->visibleTo($user)->findOrFail($data['project_id']);
            $this->authorize('createTask', $project);
        }

        if (mb_strlen(trim(strip_tags($data['description'] ?? ''))) < 10) {
            throw ValidationException::withMessages([
                'description' => 'Опишите задачу подробнее — хотя бы 10 символов.',
            ]);
        }

        $task = $action->execute($data, $user);

        $this->customFields->sync($task, $customValues, $user);
        $consents->record($user, 'client-task', $request, ['task_id' => $task->id]);

        return redirect()->route('client.tasks.show', $task)
            ->with('success', 'Заявка отправлена.');
    }

    public function edit(Request $request, Task $task): Response
    {
        $this->authorize('update', $task);

        $task->load(['project:id,key,name,color', 'formTemplate']);

        return Inertia::render('Client/Tasks/Edit', [
            'task' => $task,
            'projects' => Project::query()
                ->visibleTo($request->user())
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'color']),
            'priorities' => Options::priorities(),
        ]);
    }

    public function update(Request $request, Task $task, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'priority' => ['sometimes', 'string'],
            'deadline' => ['nullable', 'date'],
            'form_data' => ['nullable', 'array'],
        ]);

        if (! empty($data['project_id'])) {
            Project::query()->visibleTo($request->user())->findOrFail($data['project_id']);
        }

        $oldStatus = $task->status;
        $task->fill([
            'title' => $data['title'],
            'description' => $sanitizer->sanitize($data['description'] ?? ''),
            'project_id' => $data['project_id'] ?? null,
            'priority' => $data['priority'] ?? $task->priority->value,
            'deadline' => $data['deadline'] ?? null,
            'form_data' => $task->form_template_id ? ($data['form_data'] ?? []) : $task->form_data,
            'status' => TaskStatus::PendingReview,
        ])->save();

        if ($oldStatus !== TaskStatus::PendingReview) {
            TaskStatusChanged::dispatch($task->fresh(), $oldStatus, TaskStatus::PendingReview, $request->user(), 'Задача отредактирована клиентом.');
        }

        return redirect()->route('client.tasks.show', $task)
            ->with('success', 'Задача обновлена и отправлена на повторное рассмотрение.');
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'assignee',
            'project',
            'formTemplate',
            'comments.author',
            'reports' => fn ($query) => $query
                ->where(fn ($inner) => $inner->where('recipient_id', $request->user()->id)->orWhereNull('recipient_id'))
                ->with(['author:id,name', 'recipient:id,name', 'screenshot', 'items.screenshot']),
            'invoices',
            'statusHistory.changedBy',
        ]);

        $canReview = $task->status === TaskStatus::AwaitingReview;

        return Inertia::render('Client/Tasks/Show', [
            'task' => $task,
            'canReview' => $canReview,
            'canEdit' => $request->user()->can('update', $task),
            'customFields' => $this->customFields->serializedValues($task),
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
