<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create();
    $this->executor = User::factory()->executor()->create();
    $this->other = User::factory()->executor()->create();

    $this->ownTask = Task::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'assignee_id' => $this->admin->id,
        'status' => TaskStatus::InProgress,
        'title' => 'Задача админа',
        'starts_at' => '2026-03-10',
        'deadline' => '2026-03-12',
    ]);

    $this->foreignTask = Task::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'assignee_id' => $this->executor->id,
        'status' => TaskStatus::InProgress,
        'title' => 'Задача исполнителя',
        'starts_at' => '2026-03-10',
        'deadline' => '2026-03-11',
    ]);
});

test('the admin calendar counts only the admin own tasks by default', function (): void {
    $response = $this->actingAs($this->admin)
        ->getJson(route('calendar.activity', ['month' => '2026-03']))
        ->assertOk();

    expect($response->json('days'))->toMatchArray([
        '2026-03-10' => 1,
        '2026-03-11' => 1,
        '2026-03-12' => 1,
    ]);
});

test('the admin can look at another employee workload', function (): void {
    $response = $this->actingAs($this->admin)
        ->getJson(route('calendar.activity', ['month' => '2026-03', 'assignee_id' => $this->executor->id]))
        ->assertOk();

    expect($response->json('days'))->toMatchArray([
        '2026-03-10' => 1,
        '2026-03-11' => 1,
    ]);
});

test('the executor sees own calendar and cannot request a foreign one', function (): void {
    $this->actingAs($this->executor)
        ->getJson(route('calendar.activity', ['month' => '2026-03']))
        ->assertOk()
        ->assertJsonPath('days.2026-03-10', 1);

    $this->actingAs($this->executor)
        ->getJson(route('calendar.activity', ['month' => '2026-03', 'assignee_id' => $this->other->id]))
        ->assertForbidden();
});

test('the day tasks modal is scoped to the same assignee', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('calendar.tasks', ['date' => '2026-03-10']))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Задача админа');

    $this->actingAs($this->admin)
        ->getJson(route('calendar.tasks', ['date' => '2026-03-10', 'assignee_id' => $this->executor->id]))
        ->assertOk()
        ->assertJsonPath('tasks.0.title', 'Задача исполнителя');

    $this->actingAs($this->executor)
        ->getJson(route('calendar.tasks', ['date' => '2026-03-10', 'assignee_id' => $this->admin->id]))
        ->assertForbidden();
});

test('the admin dashboard and widget zone expose the employee picker data', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('currentUserId', $this->admin->id)
            ->has('team', 3));

    $this->actingAs($this->admin)
        ->getJson(route('admin.widget-zone.data'))
        ->assertOk()
        ->assertJsonPath('currentUserId', $this->admin->id)
        ->assertJsonCount(3, 'team');
});

test('completed and rejected tasks do not count as workload', function (): void {
    Task::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'assignee_id' => $this->admin->id,
        'status' => TaskStatus::Completed,
        'title' => 'Сделанная задача',
        'starts_at' => '2026-03-10',
        'deadline' => '2026-03-12',
    ]);

    Task::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'assignee_id' => $this->admin->id,
        'status' => TaskStatus::RejectedByAdmin,
        'title' => 'Отклонённая задача',
        'starts_at' => '2026-03-10',
        'deadline' => '2026-03-12',
    ]);

    // Считается только задача в работе.
    $this->actingAs($this->admin)
        ->getJson(route('calendar.activity', ['month' => '2026-03']))
        ->assertOk()
        ->assertJsonPath('days.2026-03-10', 1)
        ->assertJsonPath('days.2026-03-11', 1)
        ->assertJsonPath('days.2026-03-12', 1);

    $this->actingAs($this->admin)
        ->getJson(route('calendar.tasks', ['date' => '2026-03-10']))
        ->assertOk()
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Задача админа');

    $this->actingAs($this->admin)
        ->getJson(route('calendar.intervals'))
        ->assertOk()
        ->assertJsonCount(1, 'intervals');
});
