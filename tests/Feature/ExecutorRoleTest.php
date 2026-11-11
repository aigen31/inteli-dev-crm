<?php

declare(strict_types=1);

use App\Enums\ProjectDelegation;
use App\Enums\ProjectMemberRole;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an admin can create an executor account from the team section', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.team.store'), [
            'name' => 'Executor One',
            'email' => 'executor-one@example.com',
            'password' => 'password123',
        ])
        ->assertRedirect(route('admin.team.index'));

    $executor = User::query()->where('email', 'executor-one@example.com')->firstOrFail();

    expect($executor->role)->toBe(UserRole::Executor)
        ->and($executor->isExecutor())->toBeTrue();
});

test('an admin can assign an executor to a task and use themselves as the client', function (): void {
    $admin = User::factory()->admin()->create();
    $executor = User::factory()->executor()->create();

    $this->actingAs($admin)
        ->post(route('admin.tasks.store'), [
            'client_id' => $admin->id,
            'assignee_id' => $executor->id,
            'title' => 'Executor task',
            'description' => 'A task for the executor.',
            'type' => 'one_time',
            'priority' => 'medium',
            'consent' => true,
        ])
        ->assertSessionHasNoErrors();

    $task = Task::query()->where('title', 'Executor task')->firstOrFail();

    expect($task->client_id)->toBe($admin->id)
        ->and($task->assignee_id)->toBe($executor->id);
});

test('an executor sees and works only on assigned tasks', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();
    $other = User::factory()->executor()->create();

    $assigned = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => $executor->id,
        'status' => TaskStatus::Queued,
        'title' => 'My task',
    ]);
    $foreign = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => $other->id,
        'title' => 'Not mine',
    ]);

    $this->actingAs($executor)->get(route('executor.tasks.show', $assigned))->assertOk();
    $this->actingAs($executor)->get(route('executor.tasks.show', $foreign))->assertForbidden();
    $this->actingAs($executor)->get(route('executor.tasks.index'))->assertInertia(fn (Assert $page) => $page->component('Executor/Tasks/Index')->has('tasks.data', 1));

    $this->actingAs($executor)
        ->patch(route('executor.tasks.status', $assigned), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($assigned->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('an executor can update the checklist and send a report to the client', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => $executor->id,
        'status' => TaskStatus::InProgress,
        'checklist' => [['text' => 'First step', 'done' => false]],
    ]);

    $this->actingAs($executor)
        ->patch(route('executor.tasks.checklist', $task), [
            'checklist' => [['text' => 'First step', 'done' => true]],
        ])
        ->assertRedirect();

    expect($task->fresh()->checklist[0]['done'])->toBeTrue();

    $this->actingAs($executor)
        ->post(route('executor.tasks.reports.store', $task), [
            'title' => 'Ready',
            'content' => 'The work is ready for review.',
        ])
        ->assertRedirect();

    $report = TaskReport::query()->firstOrFail();
    expect($report->author_id)->toBe($executor->id)
        ->and($report->recipient_id)->toBe($client->id)
        ->and($report->task_id)->toBe($task->id);
});

test('executor-only routes are closed to clients and admins', function (): void {
    $executor = User::factory()->executor()->create();
    $client = User::factory()->client()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($client)->get(route('executor.dashboard'))->assertForbidden();
    $this->actingAs($admin)->get(route('executor.dashboard'))->assertForbidden();
});

test('one user can be a client in one project and an executor in another', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->client()->create();
    $clientProject = Project::create([
        'key' => 'CLI',
        'name' => 'Client side',
        'client_id' => $user->id,
        'created_by' => $admin->id,
    ]);
    $executorProject = Project::create([
        'key' => 'EXE',
        'name' => 'Executor side',
        'client_id' => $admin->id,
        'created_by' => $admin->id,
    ]);

    $executorProject->members()->create([
        'user_id' => $user->id,
        'role' => ProjectMemberRole::Member,
        'delegation' => ProjectDelegation::Executor,
        'added_by' => $admin->id,
    ]);

    $task = Task::factory()->create([
        'client_id' => $admin->id,
        'project_id' => $executorProject->id,
        'created_by' => $admin->id,
        'assignee_id' => $user->id,
        'status' => TaskStatus::Queued,
    ]);

    expect($user->hasClientDelegation())->toBeTrue()
        ->and($user->hasExecutorDelegation())->toBeTrue();

    $this->actingAs($user)->get(route('executor.tasks.index'))->assertOk();
    $this->actingAs($user)->get(route('client.tasks.index'))->assertOk();
    $this->actingAs($user)->patch(route('executor.tasks.status', $task), ['status' => 'in_progress'])->assertRedirect();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('the project access panel stores a delegation per member', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = Project::create([
        'key' => 'DEL',
        'name' => 'Delegation project',
        'client_id' => $admin->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.projects.members.store', $project), [
            'user_id' => $client->id,
            'role' => 'member',
            'delegation' => 'executor',
        ])
        ->assertRedirect();

    expect($project->members()->where('user_id', $client->id)->first()?->delegation->value)->toBe('executor');

    $this->actingAs($admin)
        ->patch(route('admin.projects.members.update', [$project, $project->members()->first()]), [
            'role' => 'member',
            'delegation' => 'client',
        ])
        ->assertRedirect();

    expect($project->members()->where('user_id', $client->id)->first()?->delegation->value)->toBe('client');
});

test('an executor and an admin can return a task from awaiting review to work', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $executor = User::factory()->executor()->create();
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'assignee_id' => $executor->id,
        'status' => TaskStatus::AwaitingReview,
    ]);

    $this->actingAs($executor)
        ->patch(route('executor.tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect();
    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);

    $task->update(['status' => TaskStatus::AwaitingReview]);
    $this->actingAs($admin)
        ->patch(route('admin.tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect();
    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});
