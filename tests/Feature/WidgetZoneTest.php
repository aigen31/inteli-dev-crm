<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can load widget zone data and save its own layout', function (): void {
    $admin = User::factory()->admin()->create();
    $widgets = [
        ['id' => 'activity_calendar', 'w' => 3, 'h' => 2],
        ['id' => 'revenue', 'w' => 1, 'h' => 1],
    ];

    $this->actingAs($admin)
        ->getJson(route('admin.widget-zone.data'))
        ->assertOk()
        ->assertJsonStructure(['counters', 'monthlyRevenue', 'focusTasks', 'upcomingDeadlines', 'recentHistory']);

    $this->actingAs($admin)
        ->patchJson(route('admin.widget-zone.layout'), ['widgets' => $widgets])
        ->assertOk();

    expect($admin->fresh()->widget_zone_layout)->toBe($widgets);
});

test('a client cannot use the widget zone', function (): void {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->getJson(route('admin.widget-zone.data'))->assertForbidden();
});
