<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\ProjectMember;
use App\Models\ProjectStudy;
use App\Models\User;
use App\Services\Ai\KernelClient;
use App\Services\ProjectService;
use App\Services\SystemProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly SystemProjectService $systemProjects,
    ) {}

    public function index(Request $request): Response
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->with('client:id,name')
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed')])
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($request->input('q'), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(! $request->boolean('archived'), fn ($query) => $query->whereNull('archived_at'))
            ->orderByRaw("case when status = 'active' then 0 when status = 'on_hold' then 1 else 2 end")
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => $this->summary($project));

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects,
            'systemProjects' => $this->systemProjects->summaries(),
            'filters' => $request->only('status', 'client_id', 'q', 'archived'),
            'statuses' => $this->statusOptions(),
            'clients' => User::query()->where('role', 'client')->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'all' => Project::query()->whereNull('archived_at')->count(),
                'active' => Project::query()->where('status', ProjectStatus::Active)->whereNull('archived_at')->count(),
                'on_hold' => Project::query()->where('status', ProjectStatus::OnHold)->whereNull('archived_at')->count(),
                'completed' => Project::query()->where('status', ProjectStatus::Completed)->whereNull('archived_at')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Projects/Create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $project = $this->projects->create($request->user(), $data);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Проект создан.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['client:id,name,email', 'creator:id,name', 'studies' => fn ($query) => $query->with('actor:id,name')->limit(10)]);

        $tasks = $project->tasks()
            ->with(['assignee:id,name', 'client:id,name'])
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('Admin/Projects/Show', [
            'project' => [
                ...$this->detail($project),
                'members' => $this->members($project),
                'invites' => $this->invites($project),
                'can' => [
                    'update' => $request->user()->can('update', $project),
                    'manage_members' => $request->user()->can('manageMembers', $project),
                ],
            ],
            'availableMembers' => $this->availableMembers($project),
            'inviteRoles' => collect(ProjectMemberRole::cases())->map(fn (ProjectMemberRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'hint' => $role->hint(),
            ]),
            'tasks' => $tasks,
            'progress' => $this->projects->progress($project),
            'studies' => $project->studies->map(fn (ProjectStudy $study): array => $this->studyPayload($study)),
            'statuses' => $this->statusOptions(),
            'priorities' => collect(TaskPriority::cases())->map(fn (TaskPriority $priority): array => [
                'value' => $priority->value,
                'label' => $priority->label(),
            ]),
            'clients' => User::query()->where('role', 'client')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Admin/Projects/Edit', [
            'project' => $this->detail($project),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $this->validated($request, $project);

        $this->projects->update($project, $data);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Проект обновлён.');
    }

    public function archive(Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        $this->projects->archive($project);

        return redirect()->route('admin.projects.index')->with('success', 'Проект в архиве.');
    }

    public function storeMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ]);

        $user = User::query()->whereKey($data['user_id'])->firstOrFail();

        if ($user->id === $project->client_id) {
            return back()->with('error', 'Владелец проекта уже имеет доступ.');
        }

        $project->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => $data['role'], 'added_by' => $request->user()->id],
        );

        return back()->with('success', 'Доступ выдан: '.$user->name.'.');
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        abort_unless($member->project_id === $project->id, 404);

        $data = $request->validate([
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ]);

        $member->update(['role' => $data['role']]);

        return back()->with('success', 'Роль обновлена.');
    }

    public function destroyMember(Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        abort_unless($member->project_id === $project->id, 404);

        $name = $member->user?->name ?? 'участник';
        $member->delete();

        return back()->with('success', 'Доступ отозван: '.$name.'.');
    }

    public function study(Request $request, Project $project, KernelClient $kernel): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'focus' => ['nullable', 'string', 'max:300'],
        ]);

        if (blank($project->site_url)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'У проекта не указан сайт.'], 422);
            }

            return back()->with('error', 'У проекта не указан сайт.');
        }

        $study = $project->studies()->create([
            'actor_id' => $request->user()->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $payload = $kernel->startStudy($request->user(), [
                'url' => $project->site_url,
                'focus' => $data['focus'] ?? $project->name,
                'agents_md' => $project->agents_md,
                'instructions' => $project->instructions,
            ]);
        } catch (AiException $exception) {
            $study->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Не удалось запустить изучение: '.$exception->getMessage()], 422);
            }

            return back()->with('error', 'Не удалось запустить изучение: '.$exception->getMessage());
        }

        $study->update([
            'kernel_study_id' => $payload['id'] ?? null,
            'events' => $payload['events'] ?? [],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'study' => $this->studyPayload($study->refresh()),
            ]);
        }

        return back()->with('success', 'Изучение сайта запущено.');
    }

    public function studyStatus(Request $request, Project $project, ProjectStudy $study, KernelClient $kernel): JsonResponse
    {
        abort_unless($study->project_id === $project->id, 404);

        if ($study->isRunning() && $study->kernel_study_id !== null) {
            try {
                $actor = $study->actor_id !== null ? User::query()->find($study->actor_id) : null;
                $payload = $kernel->study($actor ?? $request->user(), $study->kernel_study_id);

                $status = (string) ($payload['status'] ?? $study->status);
                $patch = [
                    'status' => $status,
                    'events' => $payload['events'] ?? $study->events,
                    'agents_md' => $payload['agents_md'] ?? $study->agents_md,
                    'notes' => $payload['notes'] ?? $study->notes,
                    'error' => $payload['error'] ?? $study->error,
                ];

                if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
                    $patch['finished_at'] = now();
                }

                $study->update($patch);

                if ($status === 'completed' && filled($patch['agents_md'] ?? null)) {
                    $project->update([
                        'agents_md' => $patch['agents_md'],
                        'studied_at' => now(),
                    ]);
                }
            } catch (AiException $exception) {
                $study->update([
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                    'finished_at' => now(),
                ]);
            }
        }

        return response()->json([
            'study' => $this->studyPayload($study->refresh()),
            'project' => $this->detail($project->refresh()),
        ]);
    }

    public function cancelStudy(Request $request, Project $project, ProjectStudy $study, KernelClient $kernel): JsonResponse
    {
        abort_unless($study->project_id === $project->id, 404);

        if ($study->isRunning() && $study->kernel_study_id !== null) {
            try {
                $actor = $study->actor_id !== null ? User::query()->find($study->actor_id) : null;
                $kernel->cancelStudy($actor ?? $request->user(), $study->kernel_study_id);
            } catch (AiException) {
                // The runner may already be gone; the local state still matters.
            }
        }

        $study->update([
            'status' => 'cancelled',
            'finished_at' => now(),
        ]);

        return response()->json([
            'study' => $this->studyPayload($study->refresh()),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invites(Project $project): array
    {
        return $project->invites()
            ->with(['creator:id,name', 'acceptedUser:id,name'])
            ->limit(20)
            ->get()
            ->map(fn (ProjectInvite $invite): array => [
                'id' => $invite->id,
                'role' => $invite->role->value,
                'role_label' => $invite->role->label(),
                'email' => $invite->email,
                'status' => $invite->status(),
                'status_label' => $invite->statusLabel(),
                'expires_at' => $invite->expires_at->toIso8601String(),
                'used_at' => $invite->used_at?->toIso8601String(),
                'accepted_user' => $invite->acceptedUser?->only('id', 'name'),
                'created_by' => $invite->creator?->only('id', 'name'),
                'created_at' => $invite->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function members(Project $project): array
    {
        return $project->members()
            ->with('user:id,name,email,role')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProjectMember $member): array => [
                'id' => $member->id,
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'user' => $member->user ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                ] : null,
                'created_at' => $member->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function availableMembers(Project $project): array
    {
        return User::query()
            ->where('role', 'client')
            ->where('is_active', true)
            ->whereKeyNot($project->client_id ?? '00000000-0000-0000-0000-000000000000')
            ->whereNotIn('id', $project->members()->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'key' => ['nullable', 'string', 'max:8', 'regex:/^[A-Za-z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'color' => ['required', Rule::in(['violet', 'sage', 'sky', 'peach', 'ink'])],
            'site_url' => ['nullable', 'url', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:8000'],
            'agents_md' => ['nullable', 'string', 'max:60000'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'clients' => User::query()->where('role', 'client')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => $this->statusOptions(),
            'priorities' => collect(TaskPriority::cases())->map(fn (TaskPriority $priority): array => [
                'value' => $priority->value,
                'label' => $priority->label(),
            ]),
            'colors' => [
                ['value' => 'violet', 'label' => 'Фиалка'],
                ['value' => 'sage', 'label' => 'Шалфей'],
                ['value' => 'sky', 'label' => 'Небо'],
                ['value' => 'peach', 'label' => 'Персик'],
                ['value' => 'ink', 'label' => 'Чернила'],
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    private function statusOptions(): array
    {
        return collect(ProjectStatus::cases())->map(fn (ProjectStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Project $project): array
    {
        $total = (int) ($project->tasks_count ?? 0);
        $completed = (int) ($project->completed_tasks_count ?? 0);

        return [
            'id' => $project->id,
            'key' => $project->key,
            'name' => $project->name,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'status_tone' => $project->status->tone(),
            'priority' => $project->priority->value,
            'color' => $project->color,
            'site_url' => $project->site_url,
            'client' => $project->client ? ['id' => $project->client->id, 'name' => $project->client->name] : null,
            'tasks_count' => $total,
            'completed_count' => $completed,
            'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'studied_at' => $project->studied_at?->toDateString(),
            'due_at' => $project->due_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Project $project): array
    {
        return [
            ...$this->summary($project),
            'description' => $project->description,
            'instructions' => $project->instructions,
            'agents_md' => $project->agents_md,
            'starts_at' => $project->starts_at?->toDateString(),
            'created_at' => $project->created_at?->toIso8601String(),
            'client' => $project->client ? [
                'id' => $project->client->id,
                'name' => $project->client->name,
                'email' => $project->client->email,
            ] : null,
            'creator' => $project->creator ? ['id' => $project->creator->id, 'name' => $project->creator->name] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studyPayload(ProjectStudy $study): array
    {
        return [
            'id' => $study->id,
            'status' => $study->status,
            'events' => $study->events ?? [],
            'agents_md' => $study->agents_md,
            'notes' => $study->notes,
            'error' => $study->error,
            'started_at' => $study->started_at?->toIso8601String(),
            'finished_at' => $study->finished_at?->toIso8601String(),
            'actor' => $study->relationLoaded('actor') && $study->actor ? ['id' => $study->actor->id, 'name' => $study->actor->name] : null,
        ];
    }
}
