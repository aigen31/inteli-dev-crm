<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can save a dashboard widget layout', function (): void {
    $admin = User::factory()->admin()->create();
    $widgets = [
        ['id' => 'focus', 'w' => 2, 'h' => 2],
        ['id' => 'revenue', 'w' => 4, 'h' => 1],
    ];

    $this->actingAs($admin)
        ->patchJson(route('dashboard.layout'), ['widgets' => $widgets])
        ->assertOk();

    expect($admin->fresh()->dashboard_layout)->toBe($widgets);
});

test('dashboard widgets and sizes are validated', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patchJson(route('dashboard.layout'), ['widgets' => [['id' => 'unknown', 'w' => 9, 'h' => 0]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['widgets.0.id', 'widgets.0.w', 'widgets.0.h']);
});
