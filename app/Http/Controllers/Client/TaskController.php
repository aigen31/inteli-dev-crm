<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\CustomFieldEntity;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReviewTaskRequest;
use App\Http\Requests\Client\StoreTaskRequest;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Services\CustomFieldService;
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
        $tasks = $request->user()
            ->clientTasks()
            ->with(['assignee:id,name', 'template:id,name'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Client/Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status'),
            'statuses' => Options::taskStatuses(),
            'counts' => [
                'all' => $request->user()->clientTasks()->count(),
                'active' => $request->user()->clientTasks()->whereIn('status', [
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
        return Inertia::render('Client/Tasks/Create', [
            'priorities' => Options::priorities(),
            'templates' => TaskTemplate::query()
                ->visibleTo(request()->user())
                ->with('defaultAssignee:id,name')
                ->orderByDesc('usage_count')
                ->get(),
            'fields' => collect($this->customFields->serializedDefinitions(CustomFieldEntity::Task))
                ->filter(fn (array $field) => $field['client_editable'])
                ->values(),
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTaskAction $action): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        if (! empty($data['template_id'])) {
            $template = TaskTemplate::query()->visibleTo($user)->findOrFail($data['template_id']);

            $task = $this->templates->apply($template, $user, $user, [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'deadline' => $data['deadline'] ?? null,
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
        ]);

        $task = $service->apply($available, $user, $user, $data, TaskSource::Client);

        return redirect()->route('client.tasks.show', $task)
            ->with('success', 'Заявка создана из шаблона.');
    }

    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'assignee',
            'template',
            'comments.author',
            'reports.author',
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
