<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.kernel_url' => 'http://kernel.test',
        'ai.kernel_token' => 'test-token',
        'ai.kernel_provider_token' => 'test-provider-token',
        'ai.kernel_channel' => 'crm',
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->clientA = User::factory()->client()->create();
    $this->clientB = User::factory()->client()->create();
});

function makeProject(User $owner, User $admin, array $overrides = []): Project
{
    return Project::query()->create([
        'key' => 'P'.strtoupper(substr(uniqid(), -4)),
        'name' => 'Проект '.$owner->name,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
        'client_id' => $owner->id,
        'created_by' => $admin->id,
        ...$overrides,
    ]);
}

test('a client sees only owned or invited projects', function (): void {
    $owned = makeProject($this->clientA, $this->admin);
    $invited = makeProject($this->clientA, $this->admin);
    $hidden = makeProject(User::factory()->client()->create(), $this->admin);

    ProjectMember::query()->create([
        'project_id' => $invited->id,
        'user_id' => $this->clientB->id,
        'role' => 'viewer',
        'added_by' => $this->admin->id,
    ]);

    $this->actingAs($this->clientB)
        ->get(route('client.projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Client/Projects/Index')
            ->has('projects', 1)
            ->where('projects.0.id', $invited->id));

    $this->actingAs($this->clientB)->get(route('client.projects.show', $invited))->assertOk();
    $this->actingAs($this->clientB)->get(route('client.projects.show', $owned))->assertForbidden();
    $this->actingAs($this->clientB)->get(route('client.projects.show', $hidden))->assertForbidden();

    $this->actingAs($this->clientA)->get(route('client.projects.show', $owned))->assertOk();
    $this->actingAs($this->clientA)->get(route('client.projects.show', $invited))->assertOk();
    $this->actingAs($this->clientA)->get(route('client.projects.show', $hidden))->assertForbidden();
});

test('an admin grants changes and revokes project access', function (): void {
    $project = makeProject($this->clientA, $this->admin);

    $this->actingAs($this->clientB)->get(route('client.projects.show', $project))->assertForbidden();

    $this->actingAs($this->admin)->post(route('admin.projects.members.store', $project), [
        'user_id' => $this->clientB->id,
        'role' => 'viewer',
    ])->assertRedirect();

    $member = ProjectMember::query()->where('project_id', $project->id)->firstOrFail();
    expect($member->role->value)->toBe('viewer');

    $this->actingAs($this->clientB)->get(route('client.projects.show', $project))->assertOk();

    $this->actingAs($this->admin)->patch(route('admin.projects.members.update', [$project, $member]), [
        'role' => 'member',
    ])->assertRedirect();

    expect($member->refresh()->role->value)->toBe('member');

    $this->actingAs($this->admin)->delete(route('admin.projects.members.destroy', [$project, $member]))
        ->assertRedirect();

    $this->actingAs($this->clientB)->get(route('client.projects.show', $project))->assertForbidden();
});

test('project roles control task creation', function (): void {
    $project = makeProject($this->clientA, $this->admin);

    ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $this->clientB->id,
        'role' => 'viewer',
        'added_by' => $this->admin->id,
    ]);

    expect(Gate::forUser($this->clientB)->allows('createTask', $project))->toBeFalse();
    expect(Gate::forUser($this->clientA)->allows('createTask', $project))->toBeTrue();
    expect(Gate::forUser($this->admin)->allows('createTask', $project))->toBeTrue();

    $conversation = AiConversation::query()->create([
        'user_id' => $this->clientB->id,
        'project_id' => $project->id,
        'title' => 'План',
        'last_message_at' => now(),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer test-provider-token',
        'X-Kernel-Channel' => 'crm',
    ])->postJson('/api/internal/assistant/tools/propose_task_plan', [
        'conversation_id' => $conversation->id,
        'actor' => ['id' => $this->clientB->id, 'role' => 'client'],
        'arguments' => [
            'project_id' => $project->id,
            'tasks' => [['title' => 'Задача от наблюдателя']],
        ],
    ]);

    $response->assertOk();
    expect($response->json('result.error'))->toContain('только просмотр');
});

test('a manager sees the project team but a viewer does not', function (): void {
    $project = makeProject($this->clientA, $this->admin);

    $member = ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $this->clientB->id,
        'role' => 'viewer',
        'added_by' => $this->admin->id,
    ]);

    $this->actingAs($this->clientB)->get(route('client.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('project.can.see_team', false)->has('team', 0));

    $member->update(['role' => 'manager']);

    $this->actingAs($this->clientB)->get(route('client.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('project.can.see_team', true)->has('team', 1));
});

test('a viewer cannot create a project task but a member can', function (): void {
    $project = makeProject($this->clientA, $this->admin);

    $member = ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $this->clientB->id,
        'role' => 'viewer',
        'added_by' => $this->admin->id,
    ]);

    $payload = [
        'consent' => true,
        'project_id' => $project->id,
        'title' => 'Новая задача клиента',
        'description' => 'Достаточно подробное описание задачи.',
        'priority' => 'medium',
    ];

    $this->actingAs($this->clientB)->post(route('client.tasks.store'), $payload)->assertForbidden();

    $member->update(['role' => 'member']);

    $this->actingAs($this->clientB)->post(route('client.tasks.store'), $payload)->assertRedirect();

    $task = Task::query()->where('project_id', $project->id)->firstOrFail();
    expect($task->client_id)->toBe($this->clientB->id);
    expect($task->number)->toBe(1);
});

test('removing project access hides its tasks from the client', function (): void {
    $project = makeProject($this->clientA, $this->admin);
    $member = ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $this->clientB->id,
        'role' => 'viewer',
        'added_by' => $this->admin->id,
    ]);

    $task = Task::query()->create([
        'client_id' => $this->clientB->id,
        'project_id' => $project->id,
        'created_by' => $this->admin->id,
        'title' => 'Задача клиента B',
        'description' => 'Описание',
        'type' => 'one_time',
        'status' => 'pending_review',
        'priority' => 'medium',
    ]);

    $this->actingAs($this->clientB)->get(route('client.tasks.show', $task))->assertOk();

    $member->delete();

    $this->actingAs($this->clientB)->get(route('client.tasks.show', $task))->assertForbidden();
    $this->actingAs($this->clientB)
        ->get(route('client.tasks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks.data', 0));

    $this->actingAs($this->admin)->get(route('admin.tasks.show', $task))->assertOk();
});

test('a revoked project is not injected into the assistant prompt', function (): void {
    $project = makeProject($this->clientA, $this->admin, [
        'agents_md' => "# СЕКРЕТ\n\nВнутренняя инструкция проекта.",
    ]);

    $member = ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $this->clientB->id,
        'role' => 'viewer',
        'added_by' => $this->admin->id,
    ]);

    $conversation = AiConversation::query()->create([
        'user_id' => $this->clientB->id,
        'project_id' => $project->id,
        'title' => 'Проект',
        'last_message_at' => now(),
    ]);

    $profile = $this->withHeaders([
        'Authorization' => 'Bearer test-provider-token',
        'X-Kernel-Channel' => 'crm',
    ])->getJson('/api/internal/assistant/profile?actor_id='.$this->clientB->id.'&actor_role=client&conversation_id='.$conversation->id);

    $profile->assertOk();
    expect($profile->json('system'))->toContain('СЕКРЕТ');

    $member->delete();

    $profile = $this->withHeaders([
        'Authorization' => 'Bearer test-provider-token',
        'X-Kernel-Channel' => 'crm',
    ])->getJson('/api/internal/assistant/profile?actor_id='.$this->clientB->id.'&actor_role=client&conversation_id='.$conversation->id);

    $profile->assertOk();
    expect($profile->json('system'))->not->toContain('СЕКРЕТ');
});

test('the admin project page exposes the team and the access controls', function (): void {
    $project = makeProject($this->clientA, $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Projects/Show')
            ->where('project.can.manage_members', true)
            ->has('availableMembers', 1)
            ->where('availableMembers.0.id', $this->clientB->id));
});
