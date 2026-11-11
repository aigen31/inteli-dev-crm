<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a client can edit a task they created and it returns to review', function (): void {
    $client = User::factory()->client()->create();
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $client->id,
        'status' => TaskStatus::InProgress,
        'description' => '<p>Old description</p>',
    ]);

    $this->actingAs($client)
        ->put(route('client.tasks.update', $task), [
            'title' => 'Updated client task',
            'description' => '<p>Updated description</p>',
            'priority' => 'high',
        ])
        ->assertRedirect(route('client.tasks.show', $task));

    $task->refresh();
    expect($task->title)->toBe('Updated client task')
        ->and($task->status)->toBe(TaskStatus::PendingReview);
});

test('a client cannot edit a task created by an admin', function (): void {
    $client = User::factory()->client()->create();
    $admin = User::factory()->admin()->create();
    $task = Task::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

    $this->actingAs($client)
        ->get(route('client.tasks.edit', $task))
        ->assertForbidden();
});
