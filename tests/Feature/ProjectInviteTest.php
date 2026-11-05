<?php

declare(strict_types=1);

use App\Models\ConsentLog;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->project = Project::create([
        'key' => 'INV',
        'name' => 'Приглашённый проект',
        'client_id' => User::factory()->client()->create()->id,
        'created_by' => $this->admin->id,
    ]);
});

function inviteToken(string $url): string
{
    return basename((string) parse_url($url, PHP_URL_PATH));
}

test('an admin creates a one-time invite link and a guest registers into the project', function (): void {
    $response = $this->actingAs($this->admin)->postJson(route('admin.projects.invites.store', $this->project), [
        'role' => 'member',
        'days' => 7,
        'email' => 'invited@example.com',
    ]);

    $response->assertCreated()->assertJsonStructure(['url', 'invite' => ['id', 'role', 'email', 'expires_at']]);

    $url = $response->json('url');
    $token = inviteToken($url);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invite/Register')
            ->where('project.name', 'Приглашённый проект')
            ->where('role', 'Участник')
            ->where('email', 'invited@example.com'));

    $this->post(route('invite.store', ['token' => $token]), [
        'name' => 'Приглашённый клиент',
        'email' => 'invited@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'consent' => true,
        'privacy_ack' => true,
    ])->assertRedirect(route('client.dashboard'));

    $user = User::query()->where('email', 'invited@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);

    $invite = ProjectInvite::query()->firstOrFail();
    expect($invite->used_at)->not->toBeNull();
    expect($invite->accepted_user_id)->toBe($user->id);
    expect($this->project->members()->where('user_id', $user->id)->first()?->role->value)->toBe('member');
    expect(ConsentLog::query()->where('user_id', $user->id)->count())->toBe(2);

    $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Invite/Invalid'));
});

test('registration requires both checkboxes and a matching preset email', function (): void {
    $response = $this->actingAs($this->admin)->postJson(route('admin.projects.invites.store', $this->project), [
        'role' => 'viewer',
        'days' => 3,
        'email' => 'preset@example.com',
    ]);
    $token = inviteToken($response->json('url'));

    $payload = [
        'name' => 'Не тот человек',
        'email' => 'other@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'consent' => true,
        'privacy_ack' => true,
    ];

    $this->post(route('invite.store', ['token' => $token]), $payload)
        ->assertSessionHasErrors('email');

    $this->post(route('invite.store', ['token' => $token]), [...$payload, 'email' => 'preset@example.com', 'privacy_ack' => false])
        ->assertSessionHasErrors('privacy_ack');

    $this->post(route('invite.store', ['token' => $token]), [...$payload, 'email' => 'preset@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    expect($this->project->members()->first()?->role->value)->toBe('viewer');
});

test('expired invites are rejected and only admins can create them', function (): void {
    $this->actingAs($this->admin)->postJson(route('admin.projects.invites.store', $this->project), [
        'role' => 'member',
        'days' => 1,
    ])->assertCreated();

    ProjectInvite::query()->update(['expires_at' => now()->subMinute()]);

    $this->get(route('invite.show', ['token' => 'invalid-token']))
        ->assertInertia(fn (Assert $page) => $page->component('Invite/Invalid'));

    $this->actingAs(User::factory()->client()->create())
        ->postJson(route('admin.projects.invites.store', $this->project), [
            'role' => 'member',
            'days' => 7,
        ])->assertForbidden();
});
