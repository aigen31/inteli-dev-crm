<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('an admin can generate a password reset link', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset', $client))
        ->assertRedirect()
        ->assertSessionHas('reset_link', fn (string $link): bool => str_contains($link, '/reset-password/') && str_contains($link, urlencode($client->email)));
});

test('a non-admin cannot generate a password reset link', function (): void {
    $client = User::factory()->client()->create();
    $target = User::factory()->client()->create();

    $this->actingAs($client)
        ->post(route('admin.users.password-reset', $target))
        ->assertForbidden();
});

test('the generated link changes the password once', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $token = Password::broker()->createToken($client);

    $this->get(route('password.reset', ['token' => $token, 'email' => $client->email]))
        ->assertOk();

    $this->post(route('password.reset.store'), [
        'token' => $token,
        'email' => $client->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('login'));

    expect(Hash::check('new-password-123', $client->fresh()->password))->toBeTrue();

    $this->post(route('password.reset.store'), [
        'token' => $token,
        'email' => $client->email,
        'password' => 'another-password-123',
        'password_confirmation' => 'another-password-123',
    ])->assertSessionHasErrors('email');
});
