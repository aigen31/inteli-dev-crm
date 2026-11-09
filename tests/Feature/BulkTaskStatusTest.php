<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can change the status of several tasks at once', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $first = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::Queued,
    ]);
    $second = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::Queued,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.tasks.bulk-status'), [
            'task_ids' => [$first->id, $second->id],
            'status' => TaskStatus::InProgress->value,
            'comment' => 'Беру пачку в работу',
        ])
        ->assertRedirect();

    expect($first->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($second->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('bulk status skips tasks without the requested transition', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $queued = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::Queued,
    ]);
    $completed = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::Completed,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.tasks.bulk-status'), [
            'task_ids' => [$queued->id, $completed->id],
            'status' => TaskStatus::InProgress->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Пропущено'));

    expect($queued->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($completed->fresh()->status)->toBe(TaskStatus::Completed);
});

test('a client cannot bulk change task statuses', function (): void {
    $client = User::factory()->client()->create();
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $client->id,
        'status' => TaskStatus::Queued,
    ]);

    $this->actingAs($client)
        ->patch(route('admin.tasks.bulk-status'), [
            'task_ids' => [$task->id],
            'status' => TaskStatus::InProgress->value,
        ])
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Queued);
});
