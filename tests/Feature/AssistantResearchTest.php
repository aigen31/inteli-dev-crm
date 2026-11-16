<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use App\Services\Ai\CrmToolbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.kernel_url' => 'https://kernel.test',
        'ai.kernel_token' => 'test-kernel-token',
        'ai.kernel_provider_token' => 'test-provider-token',
        'ai.kernel_channel' => 'crm',
        'ai.kernel_timeout' => 30,
        'ai.research_poll_ms' => 10,
    ]);

    $this->admin = User::query()->create([
        'name' => 'Администратор',
        'email' => 'research-admin@example.com',
        'password' => bcrypt('secret'),
        'role' => UserRole::Admin,
    ]);

    $this->client = User::query()->create([
        'name' => 'Клиент',
        'email' => 'research-client@example.com',
        'password' => bcrypt('secret'),
        'role' => UserRole::Client,
    ]);

    $this->project = Project::query()->create([
        'key' => 'KOR',
        'name' => 'koreatur.ru разработка',
        'client_id' => $this->client->id,
        'site_url' => 'https://koreatur.ru',
        'status' => 'active',
        'priority' => 'medium',
    ]);

    $this->toolbox = app(CrmToolbox::class);
});

test('research returns the harness answer and reports the url', function (): void {
    Http::fake([
        'kernel.test/v1/harness/runs' => Http::response(['run_id' => 'run-1', 'status' => 'running']),
        'kernel.test/v1/harness/runs/run-1' => Http::sequence()
            ->push(['run_id' => 'run-1', 'status' => 'running'])
            ->push(['run_id' => 'run-1', 'status' => 'completed', 'answer' => 'Подписка стоит 4 900 ₽ в месяц.']),
    ]);

    $result = $this->toolbox->execute('research', $this->admin, [
        'task' => 'Сколько стоит подписка?',
        'url' => 'https://koreatur.ru/pricing',
        'wait_seconds' => 5,
    ]);

    expect($result['status'])->toBe('completed')
        ->and($result['answer'])->toContain('4 900')
        ->and($result['url'])->toBe('https://koreatur.ru/pricing');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/v1/harness/runs')
        && $request['task'] === 'Сколько стоит подписка?'
        && $request['url'] === 'https://koreatur.ru/pricing');
});

test('research takes the site and context from the project', function (): void {
    Http::fake([
        'kernel.test/v1/harness/runs' => Http::response(['run_id' => 'run-2', 'status' => 'running']),
        'kernel.test/v1/harness/runs/run-2' => Http::response(['run_id' => 'run-2', 'status' => 'running']),
    ]);

    $result = $this->toolbox->execute('research', $this->admin, [
        'task' => 'Какие разделы появились на сайте?',
        'project_id' => $this->project->id,
        'wait_seconds' => 1,
    ]);

    expect($result['status'])->toBe('running')
        ->and($result['run_id'])->toBe('run-2')
        ->and($result['url'])->toBe('https://koreatur.ru');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/v1/harness/runs')
        && $request['url'] === 'https://koreatur.ru'
        && str_contains($request['task'], 'koreatur.ru разработка'));
});

test('a failed run surfaces the harness error instead of an empty answer', function (): void {
    Http::fake([
        'kernel.test/v1/harness/runs' => Http::response(['run_id' => 'run-3', 'status' => 'running']),
        'kernel.test/v1/harness/runs/run-3' => Http::response([
            'run_id' => 'run-3',
            'status' => 'failed',
            'error' => 'timeout',
        ]),
    ]);

    $result = $this->toolbox->execute('research', $this->admin, [
        'task' => 'Проверь главную',
        'wait_seconds' => 5,
    ]);

    expect($result['status'])->toBe('failed')
        ->and($result['error'])->toBe('timeout');
});

test('research_status proxies a run and clients cannot call research', function (): void {
    Http::fake([
        'kernel.test/v1/harness/runs/run-9' => Http::response([
            'run_id' => 'run-9',
            'status' => 'completed',
            'answer' => 'Готово.',
        ]),
    ]);

    $result = $this->toolbox->execute('research_status', $this->admin, ['run_id' => 'run-9']);

    expect($result['status'])->toBe('completed');

    expect(fn () => $this->toolbox->execute('research', $this->client, ['task' => 'Проверь сайт']))
        ->toThrow(ValidationException::class);
});

test('research validates the task and rejects a foreign project id', function (): void {
    expect(fn () => $this->toolbox->execute('research', $this->admin, ['task' => 'мало']))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->toolbox->execute('research', $this->admin, [
        'task' => 'Проверь сайт проекта',
        'project_id' => '00000000-0000-0000-0000-000000000000',
    ]))->toThrow(ValidationException::class);
});
