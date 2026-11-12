<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can set a task start date', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = Project::query()->create([
        'key' => 'STR',
        'name' => 'Start project',
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);

    $this->actingAs($admin)->post(route('admin.tasks.store'), [
        'client_id' => $client->id,
        'project_id' => $project->id,
        'title' => 'Task with start date',
        'description' => 'A task with a planned start.',
        'type' => 'one_time',
        'priority' => 'medium',
        'starts_at' => '2026-11-20',
        'deadline' => '2026-11-25',
        'consent' => true,
    ])->assertSessionHasNoErrors();

    expect(Task::query()->firstOrFail()->starts_at->toDateString())->toBe('2026-11-20');
});

test('a client can set a start date in a free-form task', function (): void {
    $client = User::factory()->client()->create();
    $project = Project::query()->create([
        'key' => 'CST',
        'name' => 'Client start project',
        'client_id' => $client->id,
        'created_by' => $client->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);

    $this->actingAs($client)->post(route('client.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Client task with start',
        'description' => 'A detailed client request for the task.',
        'priority' => 'medium',
        'starts_at' => '2026-11-18',
        'deadline' => '2026-11-22',
        'consent' => true,
    ])->assertSessionHasNoErrors();

    expect(Task::query()->firstOrFail()->starts_at->toDateString())->toBe('2026-11-18');
});

test('a deadline cannot be earlier than the start date', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = Project::query()->create([
        'key' => 'BAD',
        'name' => 'Bad dates project',
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);

    $this->actingAs($admin)->post(route('admin.tasks.store'), [
        'client_id' => $client->id,
        'project_id' => $project->id,
        'title' => 'Bad dates',
        'description' => 'Task with invalid dates.',
        'type' => 'one_time',
        'priority' => 'medium',
        'starts_at' => '2026-11-25',
        'deadline' => '2026-11-20',
        'consent' => true,
    ])->assertSessionHasErrors('deadline');
});
