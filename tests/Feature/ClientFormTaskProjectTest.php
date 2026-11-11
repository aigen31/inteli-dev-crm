<?php

declare(strict_types=1);

use App\Models\FormTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a client form task appears in the selected project', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = Project::query()->create([
        'key' => 'PRJ',
        'name' => 'Client project',
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);
    $template = FormTemplate::query()->create([
        'key' => 'task-description-images',
        'name' => 'Описание задачи + изображения',
        'summary' => 'Описание',
        'description' => 'Описание задачи',
        'schema' => [
            'fields' => [
                ['key' => 'task_title', 'label' => 'Название', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'required' => true],
            ],
            'groups' => [],
        ],
        'is_active' => true,
    ]);

    $this->actingAs($client)
        ->post(route('client.tasks.forms.store', $template), [
            'title' => 'Задача в проекте',
            'project_id' => $project->id,
            'priority' => 'medium',
            'form_data' => [
                'fields' => ['task_title' => 'Задача', 'description' => 'Описание задачи'],
                'groups' => [],
            ],
            'consent' => true,
        ])
        ->assertRedirect();

    $task = Task::query()->firstOrFail();
    expect($task->project_id)->toBe($project->id);

    $this->actingAs($client)
        ->get(route('client.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Projects/Show')
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Задача в проекте'));
});
