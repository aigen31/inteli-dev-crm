<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calendar intervals are filtered by assignee and contain no task details', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();
    Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => $executor->id,
        'title' => 'Private task title',
        'starts_at' => '2026-11-16',
        'deadline' => '2026-11-20',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('calendar.intervals', ['assignee_id' => $executor->id]))
        ->assertOk()
        ->assertJsonPath('intervals.0.start', '2026-11-16')
        ->assertJsonPath('intervals.0.end', '2026-11-20');

    expect($response->json('intervals.0'))->not->toHaveKey('title');
});

test('a client cannot query another users calendar', function (): void {
    $client = User::factory()->client()->create();

    $this->actingAs($client)
        ->getJson(route('calendar.intervals', ['assignee_id' => User::factory()->executor()->create()->id]))
        ->assertForbidden();
});

test('tasks on a calendar date are visible only when the user can open them', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();
    $own = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $client->id,
        'assignee_id' => $executor->id,
        'title' => 'Visible client task',
        'starts_at' => '2026-11-16',
        'deadline' => '2026-11-20',
    ]);
    Task::factory()->create([
        'client_id' => $other->id,
        'created_by' => $admin->id,
        'assignee_id' => $executor->id,
        'title' => 'Someone else task',
        'starts_at' => '2026-11-16',
        'deadline' => '2026-11-20',
    ]);

    $this->actingAs($client)
        ->getJson(route('calendar.tasks', [
            'task_id' => $own->id,
            'date' => '2026-11-18',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Visible client task')
        ->assertJsonMissingPath('tasks.0.project');
});
