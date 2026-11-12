<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bulkProject(User $admin, User $client): Project
{
    return Project::query()->create([
        'key' => 'BLK',
        'name' => 'Bulk project',
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);
}

test('an admin can bulk update task fields and status', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();
    $project = bulkProject($admin, $client);
    $tasks = collect(range(1, 2))->map(fn (int $i) => Task::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::PendingReview,
        'title' => 'Bulk task '.$i,
        'description' => 'Original description',
    ]));

    $this->actingAs($admin)->patch(route('admin.tasks.bulk-update'), [
        'task_ids' => $tasks->pluck('id')->all(),
        'assignee_id' => $executor->id,
        'priority' => 'high',
        'starts_at' => '2026-11-18',
        'deadline' => '2026-11-22',
        'status' => 'queued',
    ])->assertRedirect();

    foreach ($tasks as $task) {
        $task->refresh();
        expect($task->assignee_id)->toBe($executor->id)
            ->and($task->priority->value)->toBe('high')
            ->and($task->starts_at->toDateString())->toBe('2026-11-18')
            ->and($task->status)->toBe(TaskStatus::Queued)
            ->and($task->description)->toBe('Original description');
    }
});

test('bulk update applies fields even when a status transition is unavailable', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = bulkProject($admin, $client);
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::PendingReview,
        'priority' => 'low',
    ]);

    $this->actingAs($admin)->patch(route('admin.tasks.bulk-update'), [
        'task_ids' => [$task->id],
        'priority' => 'high',
        'status' => 'completed',
    ])->assertRedirect();

    $task->refresh();
    expect($task->priority->value)->toBe('high')
        ->and($task->status)->toBe(TaskStatus::PendingReview);
});

test('a client cannot bulk update tasks', function (): void {
    $client = User::factory()->client()->create();
    $task = Task::factory()->create(['client_id' => $client->id, 'created_by' => $client->id]);

    $this->actingAs($client)->patch(route('admin.tasks.bulk-update'), [
        'task_ids' => [$task->id],
        'priority' => 'high',
    ])->assertForbidden();
});
