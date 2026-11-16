<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\CrmToolbox;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create();
    $this->executor = User::factory()->executor()->create();

    $this->task = Task::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'assignee_id' => $this->executor->id,
        'status' => TaskStatus::AwaitingReview,
        'title' => 'Приёмка',
    ]);
});

test('strict control is on by default and the executor cannot complete the task', function (): void {
    expect(app(SettingsService::class)->strictTaskControl())->toBeTrue();

    $this->actingAs($this->executor)
        ->patch(route('executor.tasks.status', $this->task), ['status' => 'completed'])
        ->assertSessionHasErrors('status');

    expect($this->task->fresh()->status)->toBe(TaskStatus::AwaitingReview);
});

test('the client still accepts the work under strict control', function (): void {
    $this->actingAs($this->client)
        ->patch(route('client.tasks.review', $this->task), ['status' => 'completed'])
        ->assertRedirect();

    expect($this->task->fresh()->status)->toBe(TaskStatus::Completed);
});

test('with strict control off the executor closes the task himself', function (): void {
    app(SettingsService::class)->putStrictTaskControl(false);

    $this->actingAs($this->executor)
        ->patch(route('executor.tasks.status', $this->task), ['status' => 'completed'])
        ->assertRedirect();

    expect($this->task->fresh()->status)->toBe(TaskStatus::Completed);
});

test('a task override keeps strict mode even when the setting is off', function (): void {
    app(SettingsService::class)->putStrictTaskControl(false);
    $this->task->forceFill(['strict_control' => true])->save();

    $this->actingAs($this->executor)
        ->patch(route('executor.tasks.status', $this->task->refresh()), ['status' => 'completed'])
        ->assertSessionHasErrors('status');

    expect($this->task->fresh()->status)->toBe(TaskStatus::AwaitingReview);
});

test('a task override allows the executor to close the task while the setting is on', function (): void {
    $this->task->forceFill(['strict_control' => false])->save();

    $this->actingAs($this->executor)
        ->patch(route('executor.tasks.status', $this->task->refresh()), ['status' => 'completed'])
        ->assertRedirect();

    expect($this->task->fresh()->status)->toBe(TaskStatus::Completed);
});

test('the soft mode does not let the executor jump over other statuses', function (): void {
    app(SettingsService::class)->putStrictTaskControl(false);

    $this->actingAs($this->executor)
        ->patch(route('executor.tasks.status', $this->task), ['status' => 'queued'])
        ->assertSessionHasErrors('status');

    expect($this->task->fresh()->status)->toBe(TaskStatus::AwaitingReview);
});

test('the admin can move a task to any status, including back from completed', function (): void {
    $this->task->forceFill(['status' => TaskStatus::Completed, 'completed_at' => now()])->save();

    $this->actingAs($this->admin)
        ->patch(route('admin.tasks.status', $this->task->refresh()), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($this->task->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('the admin sees every other status as a transition', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.tasks.show', $this->task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tasks/Show')
            ->where('strictControl.active', true)
            ->has('allowedTransitions', count(TaskStatus::cases()) - 1));
});

test('only an admin can toggle the setting', function (): void {
    $this->actingAs($this->executor)
        ->patch(route('admin.settings.tasks'), ['strict_control' => false])
        ->assertForbidden();

    $this->actingAs($this->client)
        ->patch(route('admin.settings.tasks'), ['strict_control' => false])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->patch(route('admin.settings.tasks'), ['strict_control' => false])
        ->assertRedirect();

    expect(app(SettingsService::class)->strictTaskControl())->toBeFalse();
});

test('the settings page shows the switch state', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.edit', ['tab' => 'tasks']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Index')
            ->where('taskControl.strict', true));
});

test('the brief strict override is saved from the task form', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.tasks.update', $this->task), [
            'client_id' => $this->client->id,
            'strict_control' => false,
        ])
        ->assertRedirect(route('admin.tasks.show', $this->task));

    expect($this->task->fresh()->strict_control)->toBeFalse();
});

test('Kiki respects the strict control rule', function (): void {
    $toolbox = app(CrmToolbox::class);

    $result = $toolbox->execute('update_task_status', $this->admin, [
        'task_id' => $this->task->id,
        'status' => 'completed',
    ]);

    expect($result['status'])->toBe('completed')
        ->and($this->task->fresh()->status)->toBe(TaskStatus::Completed);
});
