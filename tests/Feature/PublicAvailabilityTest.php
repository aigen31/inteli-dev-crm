<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public availability counts assigned tasks and marks busy days', function (): void {
    $this->travelTo('2026-11-24 09:00:00');

    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();

    foreach (range(1, 2) as $index) {
        Project::query()->create([
            'key' => 'AV'.$index,
            'name' => 'Availability '.$index,
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'status' => 'active',
            'priority' => 'medium',
            'color' => 'violet',
        ]);
    }

    Task::factory()->count(5)->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => $executor->id,
        'status' => TaskStatus::InProgress,
        'starts_at' => '2026-11-25',
        'deadline' => '2026-11-25',
    ]);
    Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => $executor->id,
        'status' => TaskStatus::Queued,
        'starts_at' => '2026-11-26',
        'deadline' => '2026-11-26',
    ]);
    // Без исполнителя задача не занимает график.
    Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => null,
        'status' => TaskStatus::Queued,
        'starts_at' => '2026-11-25',
        'deadline' => '2026-11-25',
    ]);

    $response = $this->getJson(route('api.public.availability'))->assertOk();

    $response->assertJsonPath('status', 'available')
        ->assertJsonPath('active_projects', 2)
        ->assertJsonPath('busy_after', 4)
        ->assertJsonPath('next_free_slot', '2026-11-24')
        ->assertJsonPath('days.1.date', '2026-11-25')
        ->assertJsonPath('days.1.load', 5)
        ->assertJsonPath('days.1.busy', true)
        ->assertJsonPath('days.2.load', 1)
        ->assertJsonPath('days.2.busy', false);
});

test('public availability reports closed when the horizon has no free workday', function (): void {
    $this->travelTo('2026-11-24 09:00:00');

    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();

    foreach (range(0, 29) as $offset) {
        $date = now()->addDays($offset)->toDateString();

        Task::factory()->count(5)->create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
            'assignee_id' => $executor->id,
            'status' => TaskStatus::InProgress,
            'starts_at' => $date,
            'deadline' => $date,
        ]);
    }

    $this->getJson(route('api.public.availability'))
        ->assertOk()
        ->assertJsonPath('status', 'full')
        ->assertJsonPath('next_free_slot', null);
});
