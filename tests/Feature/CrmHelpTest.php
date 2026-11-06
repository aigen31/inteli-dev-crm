<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Ai\CrmHelpService;
use App\Services\Ai\CrmToolbox;
use App\Services\Ai\PromptFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the catalogue covers both role interfaces and every link is local', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $help = app(CrmHelpService::class);

    $adminEntries = $help->forUser($admin);
    $clientEntries = $help->forUser($client);

    expect(collect($adminEntries)->pluck('id'))->toContain('admin.dashboard', 'admin.tasks.index', 'admin.projects.index', 'admin.clients.index', 'admin.invoices.index', 'admin.reports.index', 'admin.library', 'admin.settings', 'admin.help')
        ->and(collect($clientEntries)->pluck('id'))->toContain('client.dashboard', 'client.tasks.index', 'client.invoices.index', 'client.reports.index', 'client.profile', 'admin.help')
        ->and(collect($clientEntries)->pluck('id'))->not->toContain('admin.clients.index', 'admin.settings');

    foreach ([...$adminEntries, ...$clientEntries] as $entry) {
        expect($entry['path'])->toStartWith('/');
        expect($entry['steps'])->not->toBeEmpty();
    }
});

test('search resolves a section by Russian keywords and filters by role', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $help = app(CrmHelpService::class);

    $adminInvoices = $help->search($admin, 'где выставить счёт');
    expect($adminInvoices[0]['id'] ?? null)->toBe('admin.invoices.create');

    $clientInvoices = $help->search($client, 'где посмотреть счета');
    expect($clientInvoices[0]['id'] ?? null)->toBe('client.invoices.index');

    $clientInvite = $help->search($client, 'как пригласить в проект');
    expect(collect($clientInvite)->pluck('id'))->not->toContain('admin.projects.show');

    $accept = $help->search($client, 'как принять работу');
    expect($accept[0]['id'] ?? null)->toBe('client.tasks.show');

    expect($help->search($client, 'космос'))->toBeEmpty();
});

test('the current page is recognised from the inertia component and url', function (): void {
    $admin = User::factory()->admin()->create();
    $help = app(CrmHelpService::class);

    $current = $help->current($admin, ['page' => 'Admin/Tasks/Create', 'url' => '/admin/tasks/create']);
    expect($current['id'] ?? null)->toBe('admin.tasks.create');

    $fallback = $help->current($admin, ['url' => '/admin/invoices']);
    expect($fallback['id'] ?? null)->toBe('admin.invoices.show');

    expect($help->systemMap($admin, ['page' => 'Admin/Tasks/Create']))
        ->toContain('Карта интерфейса CRM')
        ->toContain('/admin/tasks/create')
        ->toContain('Пользователь сейчас на странице');
});

test('crm_help is exposed to both roles and returns actionable links', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $toolbox = app(CrmToolbox::class);

    foreach ([$admin, $client] as $user) {
        $names = collect($toolbox->definitions($user))->pluck('function.name');
        expect($names)->toContain('crm_help');
    }

    $result = $toolbox->execute('crm_help', $admin, ['query' => 'пригласить в проект']);
    expect($result['results'])->not->toBeEmpty();
    expect($result['results'][0])->toHaveKeys(['title', 'url', 'summary', 'steps']);
});

test('the system prompt contains the map and the current page', function (): void {
    $admin = User::factory()->admin()->create();
    $prompt = app(PromptFactory::class)->system($admin, null, ['page' => 'Admin/Tasks/Create', 'url' => '/admin/tasks/create']);

    expect($prompt)
        ->toContain('Карта интерфейса CRM')
        ->toContain('crm_help')
        ->toContain('/admin/tasks/create')
        ->toContain('Пользователь сейчас на странице');
});

test('the help page is available to both roles', function (): void {
    foreach ([User::factory()->admin()->create(), User::factory()->client()->create()] as $user) {
        $this->actingAs($user)->get(route('help.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Help/Index')
                ->has('sections')
                ->where('version', CrmHelpService::VERSION));
    }
});
