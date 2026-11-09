<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\CrmToolbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::create([
        'key' => 'BUD',
        'name' => 'Budget project',
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
    ]);
    $this->task = Task::factory()->create([
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'title' => 'Budget task',
        'description' => 'Task for budget test.',
        'budget' => 5000,
    ]);
});

test('Kiki exposes update_task with confirmation and changes the budget', function (): void {
    $toolbox = app(CrmToolbox::class);

    $names = collect($toolbox->definitions($this->admin))->pluck('function.name');
    expect($names)->toContain('update_task');
    expect($toolbox->requiresConfirmation('update_task'))->toBeTrue();

    $result = $toolbox->execute('update_task', $this->admin, [
        'task_id' => $this->task->id,
        'budget' => 12500.50,
        'priority' => 'high',
    ]);

    expect($result['budget'])->toBe('12500.50');
    expect($result['priority'])->toBe('high');
    expect($this->task->refresh()->budget)->toBe('12500.50');
});

test('budget can be cleared and the task title/description can be changed', function (): void {
    $toolbox = app(CrmToolbox::class);

    $result = $toolbox->execute('update_task', $this->admin, [
        'task_id' => $this->task->id,
        'budget' => null,
        'title' => 'Renamed task',
        'description' => 'Updated description.',
    ]);

    expect($result['budget'])->toBeNull();
    expect($result['title'])->toBe('Renamed task');
    expect($this->task->refresh()->budget)->toBeNull();
});

test('clients cannot see or execute update_task', function (): void {
    $toolbox = app(CrmToolbox::class);

    expect(collect($toolbox->definitions($this->client))->pluck('function.name'))->not->toContain('update_task');

    expect(fn () => $toolbox->execute('update_task', $this->client, [
        'task_id' => $this->task->id,
        'budget' => 1,
    ]))->toThrow(ValidationException::class);
});

test('Kiki distributes a project total equally without losing a kopeck', function (): void {
    Task::factory()->create(['client_id' => $this->client->id, 'project_id' => $this->project->id, 'title' => 'Second', 'description' => 'Second task.']);
    Task::factory()->create(['client_id' => $this->client->id, 'project_id' => $this->project->id, 'title' => 'Third', 'description' => 'Third task.']);

    $toolbox = app(CrmToolbox::class);
    expect($toolbox->requiresConfirmation('distribute_project_budget'))->toBeTrue();

    $result = $toolbox->execute('distribute_project_budget', $this->admin, [
        'project_id' => 'BUD',
        'amount' => 100,
        'strategy' => 'equal',
    ]);

    expect($result['count'])->toBe(3);
    expect(array_sum(array_map(fn (array $row): float => (float) $row['budget'], $result['allocations'])))->toBe(100.0);
    expect($result['allocations'][0]['budget'])->toBe('33.34');
    expect($result['allocations'][1]['budget'])->toBe('33.33');
    expect($result['allocations'][2]['budget'])->toBe('33.33');
});

test('Kiki supports manual budget distribution', function (): void {
    $second = Task::factory()->create(['client_id' => $this->client->id, 'project_id' => $this->project->id, 'title' => 'Second', 'description' => 'Second task.']);

    $toolbox = app(CrmToolbox::class);
    $manual = $toolbox->execute('distribute_project_budget', $this->admin, [
        'project_id' => $this->project->id,
        'amount' => 300,
        'strategy' => 'manual',
        'task_ids' => [$this->task->id, $second->id],
        'allocations' => [
            ['task_id' => $this->task->id, 'amount' => 100],
            ['task_id' => $second->id, 'amount' => 200],
        ],
    ]);

    expect($manual['allocations'][0]['budget'])->toBe('100.00');
    expect($manual['allocations'][1]['budget'])->toBe('200.00');
});

test('Kiki mass-updates any supported task field by project filter', function (): void {
    Task::factory()->create(['client_id' => $this->client->id, 'project_id' => $this->project->id, 'title' => 'Second', 'description' => 'Second task.']);
    Task::factory()->create(['client_id' => $this->client->id, 'project_id' => $this->project->id, 'title' => 'Third', 'description' => 'Third task.']);

    $toolbox = app(CrmToolbox::class);
    expect($toolbox->requiresConfirmation('update_tasks'))->toBeTrue();

    $result = $toolbox->execute('update_tasks', $this->admin, [
        'project_id' => 'BUD',
        'values' => [
            'budget' => 250.50,
            'priority' => 'high',
            'deadline' => '2026-12-31',
        ],
    ]);

    expect($result['count'])->toBe(3);
    expect($result['values'])->toMatchArray(['budget' => 250.50, 'priority' => 'high']);
    expect((float) Task::query()->where('project_id', $this->project->id)->sum('budget'))->toBe(751.5);
    expect(Task::query()->where('project_id', $this->project->id)->where('priority', 'high')->count())->toBe(3);
});

test('Kiki mass-updates status and description with task ids', function (): void {
    $toolbox = app(CrmToolbox::class);

    $result = $toolbox->execute('update_tasks', $this->admin, [
        'task_ids' => [$this->task->id],
        'values' => [
            'status' => 'queued',
            'description' => 'Mass-updated description.',
        ],
        'comment' => 'Массовая правка',
    ]);

    expect($result['count'])->toBe(1);
    expect($this->task->refresh()->status->value)->toBe('queued');
    expect($this->task->description)->toContain('Mass-updated');
});

test('Kiki rejects unknown bulk fields and clients cannot use update_tasks', function (): void {
    $toolbox = app(CrmToolbox::class);

    expect(collect($toolbox->definitions($this->client))->pluck('function.name'))->not->toContain('update_tasks');

    expect(fn () => $toolbox->execute('update_tasks', $this->admin, [
        'project_id' => 'BUD',
        'values' => ['nope' => 1],
    ]))->toThrow(ValidationException::class);

    expect(fn () => $toolbox->execute('update_tasks', $this->client, [
        'project_id' => 'BUD',
        'values' => ['budget' => 1],
    ]))->toThrow(ValidationException::class);
});

test('Kiki updates different descriptions per task and appends links', function (): void {
    $second = Task::factory()->create([
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'title' => 'Second task',
        'description' => 'Existing second description.',
    ]);

    $toolbox = app(CrmToolbox::class);
    $result = $toolbox->execute('update_tasks', $this->admin, [
        'updates' => [
            ['task_id' => $this->task->id, 'values' => ['append_description' => 'Пример: https://example.com/first']],
            ['task_id' => $second->id, 'values' => ['append_description' => 'Пример: https://example.com/second']],
        ],
    ]);

    expect($result['count'])->toBe(2);
    expect($this->task->refresh()->description)->toContain('https://example.com/first');
    expect($second->refresh()->description)->toContain('https://example.com/second');
    expect($second->description)->toContain('Existing second description.');
    expect($this->task->description)->not->toContain('https://example.com/second');
});

test('update_tasks schema supports per-task updates', function (): void {
    $toolbox = app(CrmToolbox::class);
    $definition = collect($toolbox->definitions($this->admin))->firstWhere('function.name', 'update_tasks');

    expect($definition['function']['parameters']['properties'])->toHaveKey('updates');
    expect($definition['function']['parameters']['properties']['values']['properties'])->toHaveKey('append_description');
    expect($definition['function']['parameters']['required'])->toBe([]);
});

test('Kiki resolves project keys and skips unavailable status transitions in bulk updates', function (): void {
    $otherProject = Project::create([
        'key' => 'ALT',
        'name' => 'Alternative project',
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
    ]);

    $this->task->update(['project_id' => $otherProject->id]);

    $toolbox = app(CrmToolbox::class);
    $result = $toolbox->execute('update_tasks', $this->admin, [
        'updates' => [
            [
                'task_id' => $this->task->id,
                'values' => [
                    'project_id' => 'BUD',
                    'budget' => 900,
                    'status' => 'in_progress',
                ],
            ],
        ],
    ]);

    expect($result['count'])->toBe(1)
        ->and($result['warnings'])->not->toBeEmpty()
        ->and($this->task->refresh()->project_id)->toBe($this->project->id)
        ->and((float) $this->task->budget)->toBe(900.0)
        ->and($this->task->status->value)->toBe('pending_review');
});
