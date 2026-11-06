<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can move a task to another client without a server error', function (): void {
    $admin = User::factory()->admin()->create();
    $from = User::factory()->client()->create();
    $to = User::factory()->client()->create();

    $task = Task::factory()->create([
        'client_id' => $from->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.tasks.update', $task), [
            'client_id' => $to->id,
        ])
        ->assertRedirect(route('admin.tasks.show', $task));

    expect($task->fresh()->client_id)->toBe($to->id);
});
