<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReviewTaskRequest;
use App\Http\Requests\Client\StoreTaskRequest;
use App\Models\FormTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Services\ConsentService;
use App\Services\CustomFieldService;
use App\Services\FormTemplateService;
use App\Services\TaskTemplateService;
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
        private readonly TaskTemplateService $templates,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tasks = Task::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'template:id,name', 'project:id,key,name,color'])
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
            'scenariosCount' => TaskTemplate::query()
                ->visibleTo(request()->user())
                ->count(),
        ]);
    }

    public function scenarios(): Response
    {
        return Inertia::render('Client/Tasks/Scenarios', [
            'templates' => TaskTemplate::query()
                ->visibleTo(request()->user())
                ->with('defaultAssignee:id,name')
                ->orderByDesc('usage_count')
                ->get(),
        ]);
    }

    public function storeForm(
        Request $request,
        FormTemplate $formTemplate,
        FormTemplateService $forms,
        ConsentService $consents,
    ): RedirectResponse {
        $request->validate(['consent' => ['accepted']]);

        $task = $forms->createTask(
            $formTemplate,
            $request->user(),
            [
                'title' => $request->input('title'),
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

        if (! empty($data['template_id'])) {
            $template = TaskTemplate::query()->visibleTo($user)->findOrFail($data['template_id']);

            $task = $this->templates->apply($template, $user, $user, [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'project_id' => $data['project_id'] ?? null,
            ], TaskSource::Client);
        } else {
            if (mb_strlen(trim(strip_tags($data['description'] ?? ''))) < 10) {
                throw ValidationException::withMessages([
                    'description' => 'Опишите задачу подробнее — хотя бы 10 символов.',
                ]);
            }

            $task = $action->execute($data, $user);
        }

        $this->customFields->sync($task, $customValues, $user);
        $consents->record($user, 'client-task', $request, ['task_id' => $task->id]);

        return redirect()->route('client.tasks.show', $task)
            ->with('success', 'Заявка отправлена.');
    }

    public function applyTemplate(
        Request $request,
        TaskTemplate $template,
        TaskTemplateService $service,
    ): RedirectResponse {
        $user = $request->user();
        $available = TaskTemplate::query()->visibleTo($user)->findOrFail($template->id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'consent' => ['accepted'],
        ]);

        $task = $service->apply($available, $user, $user, $data, TaskSource::Client);
        app(ConsentService::class)->record($user, 'client-task-template', $request, ['task_id' => $task->id]);

        return redirect()->route('client.tasks.show', $task)
            ->with('success', 'Заявка создана из шаблона.');
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'assignee',
            'project',
            'template',
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
