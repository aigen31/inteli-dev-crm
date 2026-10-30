<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskTemplateRequest;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Ai\TemplateGenerator;
use App\Services\TaskTemplateService;
use App\Support\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = TaskTemplate::query()
            ->with(['client:id,name', 'defaultAssignee:id,name', 'creator:id,name'])
            ->when($request->input('q'), fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('summary', 'like', "%{$search}%"),
            ))
            ->when($request->input('scope') === 'global', fn ($query) => $query->whereNull('client_id'))
            ->when($request->input('scope') === 'personal', fn ($query) => $query->whereNotNull('client_id'))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/TaskTemplates/Index', [
            'templates' => $templates,
            'filters' => $request->only('q', 'scope'),
            'clients' => User::query()
                ->where('role', UserRole::Client)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'stats' => [
                'total' => TaskTemplate::query()->count(),
                'active' => TaskTemplate::query()->where('is_active', true)->count(),
                'usage' => (int) TaskTemplate::query()->sum('usage_count'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TaskTemplates/Edit', [
            'template' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreTaskTemplateRequest $request, TaskTemplateService $service): RedirectResponse
    {
        $template = $service->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.task-templates.edit', $template)
            ->with('success', 'Шаблон создан. Теперь его можно применить к клиенту.');
    }

    public function edit(TaskTemplate $taskTemplate): Response
    {
        $taskTemplate->load(['client:id,name', 'defaultAssignee:id,name']);

        return Inertia::render('Admin/TaskTemplates/Edit', [
            'template' => $taskTemplate,
            ...$this->formOptions(),
        ]);
    }

    public function update(
        StoreTaskTemplateRequest $request,
        TaskTemplate $taskTemplate,
        TaskTemplateService $service,
    ): RedirectResponse {
        $service->update($taskTemplate, $request->validated());

        return back()->with('success', 'Шаблон сохранён.');
    }

    public function destroy(TaskTemplate $taskTemplate): RedirectResponse
    {
        $taskTemplate->delete();

        return redirect()
            ->route('admin.task-templates.index')
            ->with('success', 'Шаблон удалён.');
    }

    public function toggle(TaskTemplate $taskTemplate): RedirectResponse
    {
        $taskTemplate->update(['is_active' => ! $taskTemplate->is_active]);

        return back()->with('success', $taskTemplate->is_active ? 'Шаблон включён.' : 'Шаблон выключен.');
    }

    public function apply(
        Request $request,
        TaskTemplate $taskTemplate,
        TaskTemplateService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
            'deadline' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $client = User::query()
            ->where('role', UserRole::Client)
            ->findOrFail($data['client_id']);

        $task = $service->apply($taskTemplate, $client, $request->user(), $data);

        return redirect()
            ->route('admin.tasks.show', $task)
            ->with('success', 'Задача создана из шаблона.');
    }

    public function generate(Request $request, TemplateGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'brief' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        return response()->json($generator->generate($data['brief']));
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
            'types' => Options::taskTypes(),
            'priorities' => Options::priorities(),
            'statuses' => Options::taskStatuses(),
        ];
    }
}
