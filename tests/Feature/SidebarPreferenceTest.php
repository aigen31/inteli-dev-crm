<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can save sidebar order and visibility', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->patch(route('sidebar.update'), [
            'items' => [
                ['key' => 'admin.tasks', 'hidden' => false],
                ['key' => 'admin.dashboard', 'hidden' => true],
                ['key' => 'admin.projects', 'hidden' => false],
            ],
        ])
        ->assertRedirect();

    expect($user->fresh()->sidebar_preferences)->toBe([
        ['key' => 'admin.tasks', 'hidden' => false],
        ['key' => 'admin.dashboard', 'hidden' => true],
        ['key' => 'admin.projects', 'hidden' => false],
    ]);
});

test('sidebar preferences are validated', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->patch(route('sidebar.update'), ['items' => [['key' => '', 'hidden' => 'nope']]])
        ->assertSessionHasErrors(['items.0.key', 'items.0.hidden']);
});
