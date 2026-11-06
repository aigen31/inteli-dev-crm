<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest can choose a locale and it is shared with inertia', function (): void {
    $this->get('/locale/zh_CN')->assertRedirect();

    expect(session('locale'))->toBe('zh_CN');

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'zh_CN')
            ->has('locales', 3));
});

test('an authenticated user locale is persisted', function (): void {
    $user = User::factory()->admin()->create(['locale' => null]);

    $this->actingAs($user)->get('/locale/ru')->assertRedirect();

    expect($user->fresh()->locale)->toBe('ru')
        ->and(session('locale'))->toBe('ru');
});

test('an unsupported locale is rejected', function (): void {
    $this->get('/locale/de')->assertNotFound();
});
