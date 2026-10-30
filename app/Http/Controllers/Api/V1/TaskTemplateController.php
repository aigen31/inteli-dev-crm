<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskTemplateResource;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\TaskTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TaskTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $templates = TaskTemplate::query()
            ->visibleTo($request->user())
            ->with(['client', 'defaultAssignee'])
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100));

        return TaskTemplateResource::collection($templates);
    }

    public function show(Request $request, TaskTemplate $taskTemplate): TaskTemplateResource
    {
        abort_unless(
            $request->user()->isAdmin()
            || ($taskTemplate->is_active && $taskTemplate->is_shared && ($taskTemplate->client_id === null || $taskTemplate->client_id === $request->user()->id)),
            403,
        );

        return new TaskTemplateResource($taskTemplate->load(['client', 'defaultAssignee']));
    }

    public function store(Request $request, TaskTemplateService $service): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $template = $service->create($this->validated($request), $request->user());

        return (new TaskTemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(Request $request, TaskTemplate $taskTemplate, TaskTemplateService $service): TaskTemplateResource
    {
        abort_unless($request->user()->isAdmin(), 403);

        $service->update($taskTemplate, $this->validated($request));

        return new TaskTemplateResource($taskTemplate->refresh());
    }

    public function apply(Request $request, TaskTemplate $taskTemplate, TaskTemplateService $service): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
            'deadline' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $client = User::query()->where('role', 'client')->findOrFail($data['client_id']);
        $task = $service->apply($taskTemplate, $client, $request->user(), $data);

        return response()->json(['data' => ['task_id' => $task->id]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'default_type' => ['required', Rule::enum(TaskType::class)],
            'default_priority' => ['required', Rule::enum(TaskPriority::class)],
            'default_status' => ['required', Rule::in([TaskStatus::PendingReview->value, TaskStatus::Queued->value])],
            'default_assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'default_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);
    }
}
