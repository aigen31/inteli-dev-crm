<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('admin task list filters by calendar day and exposes workload ranges', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();

    $inside = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'title' => 'Задача в диапазоне',
        'status' => TaskStatus::InProgress,
        'starts_at' => '2026-11-20',
        'deadline' => '2026-11-25',
    ]);
    $single = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'title' => 'Задача на один день',
        'status' => TaskStatus::Queued,
        'starts_at' => null,
        'deadline' => '2026-11-22',
    ]);
    Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'title' => 'Вне диапазона',
        'status' => TaskStatus::Queued,
        'starts_at' => '2026-12-01',
        'deadline' => '2026-12-03',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tasks.index', ['date' => '2026-11-22']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Tasks/Index')
            ->where('filters.date', '2026-11-22')
            ->has('calendar', 3)
            ->where('tasks.data', fn ($tasks) => collect($tasks)->pluck('id')->sort()->values()->all() === collect([$inside->id, $single->id])->sort()->values()->all()));
});

test('client task list filters by calendar day', function (): void {
    $client = User::factory()->client()->create();

    $match = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $client->id,
        'title' => 'Моя задача на день',
        'status' => TaskStatus::Queued,
        'starts_at' => '2026-11-18',
        'deadline' => '2026-11-21',
    ]);
    Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $client->id,
        'title' => 'Моя задача позже',
        'status' => TaskStatus::Queued,
        'starts_at' => '2026-11-28',
        'deadline' => '2026-11-30',
    ]);

    $this->actingAs($client)
        ->get(route('client.tasks.index', ['date' => '2026-11-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Tasks/Index')
            ->where('filters.date', '2026-11-20')
            ->has('calendar', 2)
            ->where('tasks.data', fn ($tasks) => collect($tasks)->pluck('id')->all() === [$match->id]));
});
