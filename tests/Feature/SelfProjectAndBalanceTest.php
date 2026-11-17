<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\ProjectType;
use App\Enums\TaskStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a self project belongs to the admin and starts tasks in progress', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.projects.store'), [
        'name' => 'Внутренний проект',
        'type' => 'self',
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ])->assertRedirect();

    $project = Project::query()->latest()->firstOrFail();

    expect($project->type)->toBe(ProjectType::Self)
        ->and($project->client_id)->toBe($admin->id);

    $this->actingAs($admin)->post(route('client.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Задача без согласия',
        'description' => 'Внутренняя задача без галочки согласия.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $task = Task::query()->latest()->firstOrFail();

    expect($task->status)->toBe(TaskStatus::Queued)
        ->and($task->client_id)->toBe($admin->id);
});

test('completing a self project task skips the client review status', function (): void {
    $admin = User::factory()->admin()->create();
    $project = Project::query()->create([
        'key' => 'SELF',
        'name' => 'Внутренний',
        'type' => ProjectType::Self,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
        'client_id' => $admin->id,
        'created_by' => $admin->id,
    ]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'client_id' => $admin->id,
        'assignee_id' => $admin->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::InProgress,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.tasks.status', $task), ['status' => TaskStatus::Completed->value])
        ->assertRedirect();

    expect($task->refresh()->status)->toBe(TaskStatus::Completed);
});

test('a completed task with a budget creates a transfer invoice and lowers the client balance', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::InProgress,
        'budget' => 1500,
        'title' => 'Сайт кофейни',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.tasks.status', $task), ['status' => TaskStatus::Completed->value])
        ->assertRedirect();

    $invoice = Invoice::query()->where('task_id', $task->id)->firstOrFail();

    expect($invoice->amount)->toBe('1500.00')
        ->and($invoice->payment_method)->toBe(PaymentMethod::Transfer)
        ->and((float) $client->refresh()->balance)->toBe(-1500.0);
});

test('a zero budget task does not create an invoice and repeated completion does not duplicate it', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $free = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::InProgress,
        'budget' => 0,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.tasks.status', $free), ['status' => TaskStatus::Completed->value])
        ->assertRedirect();

    expect(Invoice::query()->where('task_id', $free->id)->exists())->toBeFalse();

    $paid = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => TaskStatus::InProgress,
        'budget' => 500,
    ]);

    $this->actingAs($admin)->patch(route('admin.tasks.status', $paid), ['status' => TaskStatus::Completed->value]);
    $this->actingAs($admin)->patch(route('admin.tasks.status', $paid), ['status' => TaskStatus::InProgress]);
    $this->actingAs($admin)->patch(route('admin.tasks.status', $paid), ['status' => TaskStatus::Completed->value]);

    expect(Invoice::query()->where('task_id', $paid->id)->count())->toBe(1)
        ->and((float) $client->refresh()->balance)->toBe(-500.0);
});

test('an admin moves the balance both ways and it can go negative', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();

    $this->actingAs($admin)
        ->post(route('admin.clients.balance', $client), ['amount' => 2000, 'description' => 'Пополнение'])
        ->assertRedirect();
    expect((float) $client->refresh()->balance)->toBe(2000.0);

    $this->actingAs($admin)
        ->post(route('admin.clients.balance', $client), ['amount' => -2500, 'description' => 'Корректировка'])
        ->assertRedirect();
    expect((float) $client->refresh()->balance)->toBe(-500.0);
});
