<?php

declare(strict_types=1);

use App\Models\NavigationVisit;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function navigationProject(User $owner, User $admin, array $overrides = []): Project
{
    return Project::query()->create([
        'key' => 'NAV'.strtoupper(substr(uniqid(), -4)),
        'name' => 'Проект навигации',
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'peach',
        'client_id' => $owner->id,
        'created_by' => $admin->id,
        ...$overrides,
    ]);
}

test('visiting a task records it and exposes it on the dashboard', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = navigationProject($client, $admin);
    $task = Task::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
        'number' => 7,
        'title' => 'Сортировка товаров по поставщикам',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tasks.show', $task))
        ->assertOk();

    $this->assertDatabaseHas('navigation_visits', [
        'user_id' => $admin->id,
        'visitable_type' => 'task',
        'visitable_id' => $task->id,
        'project_id' => $project->id,
        'path' => '/admin/tasks/'.$task->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('navigation.recent', 1)
            ->where('navigation.recent.0.type', 'task')
            ->where('navigation.recent.0.title', 'Сортировка товаров по поставщикам')
            ->where('navigation.recent.0.reference', $project->key.'-7')
            ->where('navigation.recent.0.project.key', $project->key)
            ->where('navigation.recent.0.project.color', 'peach')
            ->where('navigation.recent.0.status.value', $task->status->value)
            ->where('navigation.recent.0.url', route('admin.tasks.show', $task)));
});

test('a revisit bumps the timestamp instead of duplicating the row', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $task = Task::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)->get(route('admin.tasks.show', $task))->assertOk();
    $first = NavigationVisit::query()->sole();

    $this->travel(10)->minutes();
    $this->actingAs($admin)->get(route('admin.tasks.show', $task))->assertOk();

    expect(NavigationVisit::query()->count())->toBe(1)
        ->and(NavigationVisit::query()->sole()->visited_at->greaterThan($first->visited_at))->toBeTrue();
});

test('a visited project carries progress and is exposed for the trail', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = navigationProject($client, $admin);

    Task::factory()->count(3)->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
    ]);
    Task::factory()->create([
        'client_id' => $client->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
        'status' => 'completed',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.projects.show', $project))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('navigation.recent', 1)
            ->where('navigation.recent.0.type', 'project')
            ->where('navigation.recent.0.reference', $project->key)
            ->where('navigation.recent.0.progress.total', 4)
            ->where('navigation.recent.0.progress.completed', 1)
            ->where('navigation.recent.0.progress.percent', 25));
});

test('the history is capped and keeps the most recent stops', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();

    $tasks = Task::factory()->count(7)->create([
        'client_id' => $client->id,
        'created_by' => $admin->id,
    ]);

    foreach ($tasks as $task) {
        $this->travel(1)->minutes();
        $this->actingAs($admin)->get(route('admin.tasks.show', $task))->assertOk();
    }

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('navigation.recent', 6));
});

test('clearing the history removes only the current user records', function (): void {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $task = Task::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)->get(route('admin.tasks.show', $task))->assertOk();
    $this->actingAs($other)->get(route('admin.tasks.show', $task))->assertOk();

    $this->actingAs($admin)
        ->delete(route('navigation.clear'))
        ->assertRedirect();

    $this->assertDatabaseMissing('navigation_visits', ['user_id' => $admin->id]);
    $this->assertDatabaseHas('navigation_visits', ['user_id' => $other->id]);
});

test('a client never sees a stale visit to a task they can no longer open', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $stranger = User::factory()->client()->create();

    $task = Task::factory()->create([
        'client_id' => $stranger->id,
        'created_by' => $admin->id,
    ]);

    NavigationVisit::query()->create([
        'user_id' => $client->id,
        'visitable_type' => 'task',
        'visitable_id' => $task->id,
        'project_id' => null,
        'path' => 'tasks/'.$task->id,
        'visited_at' => now(),
    ]);

    $this->actingAs($client)
        ->get(route('client.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Dashboard')
            ->has('navigation.recent', 0));
});

test('a partial inertia reload does not create a visit', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $task = Task::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Admin/Dashboard',
            'X-Inertia-Partial-Data' => 'counters',
        ])
        ->get(route('admin.tasks.show', $task));

    expect(NavigationVisit::query()->count())->toBe(0);
});
