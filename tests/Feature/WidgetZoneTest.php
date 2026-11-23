<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can load widget panel data and save its own layout', function (): void {
    $admin = User::factory()->admin()->create();
    $widgets = [
        ['id' => 'navigation_trail', 'w' => 2, 'h' => 2],
        ['id' => 'activity_calendar', 'w' => 3, 'h' => 2],
        ['id' => 'revenue', 'w' => 1, 'h' => 1],
    ];

    $this->actingAs($admin)
        ->getJson(route('widget-zone.data'))
        ->assertOk()
        ->assertJsonStructure(['counters', 'monthlyRevenue', 'focusTasks', 'upcomingDeadlines', 'recentHistory']);

    $this->actingAs($admin)
        ->patchJson(route('widget-zone.layout'), ['widgets' => $widgets])
        ->assertOk();

    expect($admin->fresh()->widget_zone_layout)->toBe($widgets);
});

test('every user gets a scoped widget panel', function (): void {
    $executor = User::factory()->executor()->create();
    $client = User::factory()->client()->create();

    $this->actingAs($executor)
        ->getJson(route('widget-zone.data'))
        ->assertOk()
        ->assertJsonPath('isAdmin', false)
        ->assertJsonCount(0, 'team');

    $this->actingAs($client)
        ->getJson(route('widget-zone.data'))
        ->assertOk()
        ->assertJsonPath('isAdmin', false);

    // Раскладка сохраняется для каждого пользователя отдельно.
    $this->actingAs($client)
        ->patchJson(route('widget-zone.layout'), ['widgets' => [['id' => 'focus', 'w' => 2, 'h' => 2]]])
        ->assertOk();

    expect($client->fresh()->widget_zone_layout)->toBe([['id' => 'focus', 'w' => 2, 'h' => 2]]);
});
