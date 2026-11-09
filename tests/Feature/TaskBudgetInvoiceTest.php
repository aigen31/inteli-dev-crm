<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can set a task budget', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.tasks.update', $task), [
            'client_id' => $client->id,
            'budget' => 1250.50,
        ])
        ->assertRedirect(route('admin.tasks.show', $task));

    expect((float) $task->fresh()->budget)->toBe(1250.5);
});

test('an admin can create one invoice for several tasks using their budgets', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $first = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'budget' => 1000,
    ]);
    $second = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'budget' => 2500,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoices.store'), [
            'task_ids' => [$first->id, $second->id],
            'payment_method' => 'transfer',
            'consent' => true,
        ])
        ->assertSessionHasNoErrors();

    $invoice = Invoice::query()->firstOrFail();

    expect((float) $invoice->amount)->toBe(3500.0)
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->tasks()->count())->toBe(2)
        ->and($invoice->tasks()->pluck('tasks.id')->all())->toContain($first->id, $second->id);
});

test('an invoice cannot combine tasks from different clients', function (): void {
    $admin = User::factory()->admin()->create();
    $firstClient = User::factory()->client()->create();
    $secondClient = User::factory()->client()->create();
    $first = Task::factory()->create([
        'client_id' => $firstClient->id,
        'created_by' => $admin->id,
        'budget' => 1000,
    ]);
    $second = Task::factory()->create([
        'client_id' => $secondClient->id,
        'created_by' => $admin->id,
        'budget' => 2500,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoices.store'), [
            'task_ids' => [$first->id, $second->id],
            'payment_method' => 'transfer',
            'consent' => true,
        ])
        ->assertSessionHasErrors('task_ids');

    expect(Invoice::query()->count())->toBe(0);
});
