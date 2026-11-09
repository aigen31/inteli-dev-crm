<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NavigationVisit;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Remembers where a user stopped working and rebuilds the paper trail for the
 * dashboard: the last visited task or project plus the previous few stops.
 */
class NavigationHistoryService
{
    public const LIMIT = 6;

    /** How many rows a single user keeps; older stops are pruned on visit. */
    public const KEEP = 30;

    public function touch(User $user, Model $visitable, string $path): void
    {
        $projectId = match (true) {
            $visitable instanceof Task => $visitable->project_id,
            $visitable instanceof Project => $visitable->getKey(),
            default => null,
        };

        NavigationVisit::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'visitable_type' => $visitable->getMorphClass(),
                'visitable_id' => $visitable->getKey(),
            ],
            [
                'project_id' => $projectId,
                'path' => $path,
                'visited_at' => now(),
            ],
        );

        $this->prune($user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(User $user, int $limit = self::LIMIT): array
    {
        $visits = NavigationVisit::query()
            ->where('user_id', $user->getKey())
            ->latest('visited_at')
            ->limit($limit)
            ->get();

        if ($visits->isEmpty()) {
            return [];
        }

        $taskType = (new Task)->getMorphClass();
        $projectType = (new Project)->getMorphClass();

        $taskIds = $visits->where('visitable_type', $taskType)->pluck('visitable_id')->all();
        $projectIds = $visits->where('visitable_type', $projectType)->pluck('visitable_id')->all();

        $tasks = $taskIds === []
            ? collect()
            : Task::query()
                ->visibleTo($user)
                ->with('project')
                ->whereIn('id', $taskIds)
                ->get()
                ->keyBy('id');

        $projects = $projectIds === []
            ? collect()
            : Project::query()
                ->visibleTo($user)
                ->with('client:id,name')
                ->withCount([
                    'tasks',
                    'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
                ])
                ->whereIn('id', $projectIds)
                ->get()
                ->keyBy('id');

        $items = [];

        foreach ($visits as $visit) {
            $item = match ($visit->visitable_type) {
                $taskType => ($task = $tasks->get($visit->visitable_id)) instanceof Task
                    ? $this->taskPayload($user, $task, $visit)
                    : null,
                $projectType => ($project = $projects->get($visit->visitable_id)) instanceof Project
                    ? $this->projectPayload($user, $project, $visit)
                    : null,
                default => null,
            };

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function clear(User $user): void
    {
        NavigationVisit::query()->where('user_id', $user->getKey())->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(User $user, Task $task, NavigationVisit $visit): array
    {
        $project = $task->project;
        $status = $task->status;

        return [
            'id' => $visit->getKey(),
            'type' => 'task',
            'title' => $task->title,
            'subtitle' => $project?->name,
            'reference' => $task->reference(),
            'project' => $project instanceof Project ? [
                'id' => $project->getKey(),
                'key' => $project->key,
                'name' => $project->name,
                'color' => $project->color,
            ] : null,
            'status' => $status === null ? null : [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            'progress' => null,
            'url' => route($user->isAdmin() ? 'admin.tasks.show' : 'client.tasks.show', $task),
            'visited_at' => $visit->visited_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(User $user, Project $project, NavigationVisit $visit): array
    {
        $total = (int) ($project->tasks_count ?? 0);
        $completed = (int) ($project->completed_tasks_count ?? 0);

        return [
            'id' => $visit->getKey(),
            'type' => 'project',
            'title' => $project->name,
            'subtitle' => $project->client?->name,
            'reference' => $project->key,
            'project' => [
                'id' => $project->getKey(),
                'key' => $project->key,
                'name' => $project->name,
                'color' => $project->color,
            ],
            'status' => null,
            'progress' => [
                'total' => $total,
                'completed' => $completed,
                'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            ],
            'url' => route($user->isAdmin() ? 'admin.projects.show' : 'client.projects.show', $project),
            'visited_at' => $visit->visited_at?->toIso8601String(),
        ];
    }

    private function prune(User $user): void
    {
        $keep = NavigationVisit::query()
            ->where('user_id', $user->getKey())
            ->latest('visited_at')
            ->limit(self::KEEP)
            ->pluck('id');

        NavigationVisit::query()
            ->where('user_id', $user->getKey())
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
