<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a secret code unlocks only the matching feature', function (): void {
    config(['features.secret_codes.xp_theme' => 'LunaXP2001']);

    $user = User::factory()->admin()->create();

    $this->actingAs($user)->post(route('settings.unlock'), ['code' => 'wrong-code'])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->features)->toBeNull();

    $this->actingAs($user)->post(route('settings.unlock'), ['code' => 'LunaXP2001'])
        ->assertRedirect();

    expect($user->fresh()->features)->toMatchArray(['xp_theme' => true]);
});

test('theme can be changed only after the feature is unlocked', function (): void {
    $user = User::factory()->admin()->create(['features' => null]);

    $this->actingAs($user)->patch(route('settings.theme'), ['theme' => 'xp'])
        ->assertForbidden();

    $user->update(['features' => ['xp_theme' => true]]);

    $this->actingAs($user)->patch(route('settings.theme'), ['theme' => 'xp'])
        ->assertRedirect();

    expect($user->fresh()->theme)->toBe('xp');

    $this->actingAs($user)->get(route('admin.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('theme', 'xp')->where('features.xp_theme', true));
});

test('the xp theme is not shared before unlocking', function (): void {
    $user = User::factory()->client()->create(['features' => null, 'theme' => 'xp']);

    $this->actingAs($user)->get(route('client.profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('theme', 'default'));
});
