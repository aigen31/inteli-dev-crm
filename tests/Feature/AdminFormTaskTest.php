<?php

declare(strict_types=1);

use App\Models\FormTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can create a task from a structured form', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = Project::query()->create([
        'key' => 'ADF',
        'name' => 'Admin form project',
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);
    $template = FormTemplate::query()->create([
        'key' => 'task-description-images',
        'name' => 'Описание задачи + изображения',
        'summary' => 'Описание и изображения',
        'description' => 'Форма для описания задачи.',
        'schema' => [
            'fields' => [
                ['key' => 'task_title', 'label' => 'Название', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'required' => true],
            ],
            'groups' => [],
        ],
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tasks.forms.store', $template), [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'title' => 'Задача из формы',
            'priority' => 'medium',
            'form_data' => [
                'fields' => [
                    'task_title' => 'Описание задачи',
                    'description' => 'Подробное описание задачи с изображением.',
                ],
                'groups' => [],
            ],
            'consent' => true,
        ])
        ->assertRedirect();

    $task = Task::query()->firstOrFail();

    expect($task->client_id)->toBe($client->id)
        ->and($task->form_template_id)->toBe($template->id)
        ->and($task->form_data['fields']['description'])->toBe('Подробное описание задачи с изображением.')
        ->and($task->title)->toBe('Задача из формы');
});

test('an admin creates a task from a template on the regular create page', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $project = Project::query()->create([
        'key' => 'TPL',
        'name' => 'Проект',
        'client_id' => $client->id,
        'created_by' => $admin->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);
    $template = FormTemplate::query()->create([
        'key' => 'bug-report',
        'name' => 'Баг-репорт',
        'summary' => 'Баг',
        'description' => 'Баг-репорт',
        'schema' => [
            'fields' => [
                ['key' => 'what_happened', 'label' => 'Что случилось', 'type' => 'textarea', 'required' => true],
            ],
            'groups' => [],
        ],
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post(route('admin.tasks.store'), [
        'client_id' => $client->id,
        'project_id' => $project->id,
        'title' => 'Кнопка не работает',
        'description' => 'Техническая заявка из шаблона.',
        'type' => 'one_time',
        'priority' => 'high',
        'form_template_id' => $template->id,
        'form_data' => ['fields' => ['what_happened' => 'Кнопка не реагирует'], 'groups' => []],
    ])->assertRedirect();

    $task = Task::query()->latest()->firstOrFail();

    expect($task->form_template_id)->toBe($template->id)
        ->and($task->form_data['fields']['what_happened'])->toBe('Кнопка не реагирует')
        ->and($task->title)->toBe('Кнопка не работает');
});
