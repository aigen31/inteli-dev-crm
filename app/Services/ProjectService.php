<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectService
{
    private const KEY_MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $creator, array $data): Project
    {
        $project = Project::create([
            'key' => $this->uniqueKey((string) ($data['key'] ?? ''), (string) $data['name']),
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? ProjectStatus::Active,
            'priority' => $data['priority'] ?? 'medium',
            'color' => $data['color'] ?? 'violet',
            'site_url' => $data['site_url'] ?? null,
            'agents_md' => $data['agents_md'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'created_by' => $creator->id,
        ]);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data): Project
    {
        $patch = [
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? $project->status,
            'priority' => $data['priority'] ?? $project->priority,
            'color' => $data['color'] ?? $project->color,
            'site_url' => $data['site_url'] ?? null,
            'agents_md' => $data['agents_md'] ?? $project->agents_md,
            'instructions' => $data['instructions'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ];

        if (isset($data['key']) && trim((string) $data['key']) !== '') {
            $patch['key'] = $this->uniqueKey((string) $data['key'], (string) $data['name'], $project->id);
        }

        if (($patch['status'] ?? null) === ProjectStatus::Archived) {
            $patch['archived_at'] = $project->archived_at ?? now();
        } elseif ($project->archived_at !== null) {
            $patch['archived_at'] = null;
        }

        $project->update($patch);

        return $project->refresh();
    }

    public function archive(Project $project): void
    {
        $project->update([
            'status' => ProjectStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, Project>
     */
    public function forUser(User $user): Collection
    {
        return Project::query()
            ->visibleTo($user)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{total: int, completed: int, percent: int, groups: array<string, int>}
     */
    public function progress(Project $project): array
    {
        $counts = $project->tasks()
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

    public function uniqueKey(string $requested, string $name, ?string $ignoreId = null): string
    {
        $base = $this->normalizeKey($requested);

        if ($base === '') {
            $base = $this->suggestKey($name);
        }

        $base = $base !== '' ? $base : 'PRJ';
        $key = $base;
        $suffix = 2;

        while (Project::query()
            ->where('key', $key)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $key = $base.'-'.$suffix;
            $suffix++;
        }

        return $key;
    }

    public function suggestKey(string $name): string
    {
        $latin = '';

        foreach (preg_split('//u', Str::lower($name)) ?: [] as $character) {
            $latin .= self::KEY_MAP[$character] ?? $character;
        }

        $words = preg_split('/[^a-z0-9]+/', $latin) ?: [];
        $words = array_values(array_filter($words, fn (string $word) => $word !== ''));

        $key = count($words) >= 2
            ? strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 3))
            : strtoupper(substr($words[0] ?? 'PRJ', 0, 4));

        return $this->normalizeKey($key) ?: 'PRJ';
    }

    private function normalizeKey(string $key): string
    {
        $key = strtoupper(trim($key));
        $key = preg_replace('/[^A-Z0-9_-]/', '', $key) ?? '';

        return Str::limit($key, 8, '');
    }
}
