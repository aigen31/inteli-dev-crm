<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the admin reaches every admin section', function (): void {
    $admin = User::factory()->admin()->create();

    $routes = [
        'admin.dashboard',
        'admin.tasks.index',
        'admin.tasks.create',
        'admin.projects.index',
        'admin.projects.create',
        'admin.clients.index',
        'admin.clients.create',
        'admin.briefs.index',
        'admin.forms.index',
        'admin.invoices.index',
        'admin.reports.index',
        'admin.reports.create',
        'admin.settings.edit',
    ];

    foreach ($routes as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk();
    }
});

test('a regular user reaches every personal section', function (): void {
    $user = User::factory()->user()->create();

    $routes = [
        'client.dashboard',
        'client.projects.index',
        'client.tasks.index',
        'client.tasks.create',
        'client.briefs.index',
        'client.briefs.create',
        'client.invoices.index',
        'client.reports.index',
        'executor.dashboard',
        'executor.tasks.index',
        'reports.create',
        'assistant.index',
    ];

    foreach ($routes as $name) {
        $this->actingAs($user)->get(route($name))->assertOk();
    }

    // Админские разделы обычному пользователю закрыты.
    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.clients.index'))->assertForbidden();
});
