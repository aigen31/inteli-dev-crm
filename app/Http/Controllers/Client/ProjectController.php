<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\SystemProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly SystemProjectService $systemProjects,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::query()
            ->visibleTo($user)
            ->whereNull('archived_at')
            ->with('client:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'color' => $project->color,
                'description' => $project->description,
                'due_at' => $project->due_at?->toDateString(),
                'access' => $project->accessLabel($user),
                ...$this->progress($project, $user),
            ]);

        return Inertia::render('Client/Projects/Index', [
            'projects' => $projects,
            'systemProjects' => $this->systemProjects->summaries(),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $user = $request->user();

        $tasks = $project->tasks()
            ->visibleTo($user)
            ->with('assignee:id,name')
            ->latest()
            ->get();

        $role = $project->memberRole($user);
        $canSeeTeam = $role?->canSeeTeam() ?? false;

        return Inertia::render('Client/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'description' => $project->description,
                'starts_at' => $project->starts_at?->toDateString(),
                'due_at' => $project->due_at?->toDateString(),
                'access' => $project->accessLabel($user),
                'can' => [
                    'create_tasks' => $project->canCreateTasks($user),
                    'see_team' => $canSeeTeam,
                ],
            ],
            'team' => $canSeeTeam
                ? $project->members()
                    ->with('user:id,name')
                    ->get()
                    ->map(fn ($member): array => [
                        'name' => $member->user?->name,
                        'role' => $member->role->label(),
                    ])
                    ->all()
                : [],
            'tasks' => $tasks,
            'progress' => $this->progress($project, $user),
        ]);
    }

    /**
     * @return array{total: int, completed: int, percent: int, groups: array<string, int>}
     */
    private function progress(Project $project, User $user): array
    {
        $counts = $project->tasks()
            ->where('client_id', $user->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $groups = ['backlog' => 0, 'started' => 0, 'completed' => 0, 'cancelled' => 0];

        foreach ($counts as $status => $count) {
            $status = TaskStatus::tryFrom((string) $status);
            $count = (int) $count;

            if ($status === null) {
                continue;
            }

            match ($status) {
                TaskStatus::PendingReview, TaskStatus::Queued => $groups['backlog'] += $count,
                TaskStatus::InProgress, TaskStatus::AwaitingReview => $groups['started'] += $count,
                TaskStatus::Completed => $groups['completed'] += $count,
                TaskStatus::RejectedByClient, TaskStatus::RejectedByAdmin => $groups['cancelled'] += $count,
            };
        }

        $total = array_sum($groups);
        $completed = $groups['completed'];

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'groups' => $groups,
        ];
    }
}
