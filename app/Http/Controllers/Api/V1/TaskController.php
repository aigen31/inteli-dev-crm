<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\CustomFieldEntity;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\CustomFieldService;
use App\Services\HtmlSanitizer;
use App\Services\TaskTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly TaskTemplateService $templates,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tasks = Task::query()
            ->with(['client', 'assignee', 'template'])
            ->with('customFieldValues.customField')
            ->when($user->isClient(), fn ($query) => $query->where('client_id', $user->id))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('client_id') && $user->isAdmin(), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($request->input('q'), fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($request->input('filter'), fn ($query, $filters) => $this->customFields->applyFilters($query, CustomFieldEntity::Task, (array) $filters))
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100));

        return TaskResource::collection($tasks);
    }

    public function show(Request $request, Task $task): TaskResource
    {
        $this->authorizeTask($request, $task);

        $task->load(['client', 'assignee', 'template', 'customFieldValues.customField']);

        return new TaskResource($task);
    }

    public function store(Request $request, CreateTaskAction $action): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'client_id' => [$user->isAdmin() ? 'required' : 'nullable', 'uuid', 'exists:users,id'],
            'template_id' => ['nullable', 'uuid', 'exists:task_templates,id'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        $client = $user->isClient()
            ? $user
            : User::query()->where('role', 'client')->findOrFail($data['client_id']);

        if (! empty($data['template_id'])) {
            $template = TaskTemplate::query()->visibleTo($user)->findOrFail($data['template_id']);
            $task = $this->templates->apply($template, $client, $user, [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
            ], TaskSource::Api);
        } else {
            $task = $action->execute([
                ...$data,
                'client_id' => $client->id,
                'source' => TaskSource::Api,
            ], $user);
        }

        $this->customFields->sync($task, $customValues, $user);
        $task->load(['client', 'assignee', 'template', 'customFieldValues.customField']);

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function update(Request $request, Task $task): TaskResource
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'string', 'min:3'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->sanitize($data['description']);
        }

        $task->update($data);
        $this->customFields->sync($task, $customValues);

        return new TaskResource($task->refresh()->load(['client', 'assignee', 'template', 'customFieldValues.customField']));
    }

    public function status(Request $request, Task $task): TaskResource
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        app(ChangeTaskStatusAction::class)->execute(
            $task,
            TaskStatus::from($data['status']),
            $request->user(),
            $data['comment'] ?? null,
        );

        return new TaskResource($task->refresh()->load(['client', 'assignee', 'template', 'customFieldValues.customField']));
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        if ($request->user()->isClient() && $task->client_id !== $request->user()->id) {
            abort(403);
        }
    }
}
