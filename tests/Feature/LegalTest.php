<?php

declare(strict_types=1);

use App\Models\ConsentLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->client = User::factory()->client()->create();
});

test('legal pages are public and readable', function (): void {
    foreach (['legal.privacy' => 'Политика в отношении обработки персональных данных', 'legal.consent' => 'Согласие на обработку персональных данных', 'legal.requisites' => 'Реквизиты и контакты'] as $route => $text) {
        $this->get(route($route))
            ->assertOk()
            ->assertSee($text, false)
            ->assertSee('contact@inteli-dev.ru', false);
    }
});

test('a client task requires consent and is recorded', function (): void {
    $payload = [
        'title' => 'Нужна помощь с сайтом',
        'description' => 'Подробное описание задачи для проверки.',
        'priority' => 'medium',
    ];

    $this->actingAs($this->client)->post(route('client.tasks.store'), $payload)
        ->assertSessionHasErrors('consent');

    expect(Task::query()->count())->toBe(0);

    $this->actingAs($this->client)->post(route('client.tasks.store'), [...$payload, 'consent' => true])
        ->assertRedirect();

    $task = Task::query()->firstOrFail();
    expect($task->client_id)->toBe($this->client->id);

    $log = ConsentLog::query()->firstOrFail();
    expect($log->user_id)->toBe($this->client->id);
    expect($log->form)->toBe('client-task');
    expect($log->version)->toBe('2026-11-06');
});

test('profile consent is required once and then remembered', function (): void {
    $payload = ['name' => 'Клиент', 'email' => $this->client->email, 'phone' => '+7 999 000-00-00'];

    $this->actingAs($this->client)->put(route('client.profile.update'), $payload)
        ->assertSessionHasErrors('consent');

    $this->actingAs($this->client)->put(route('client.profile.update'), [...$payload, 'consent' => true])
        ->assertRedirect();

    expect(ConsentLog::query()->where('form', 'client-account')->count())->toBe(1);

    $this->actingAs($this->client)->put(route('client.profile.update'), $payload)
        ->assertRedirect();

    expect(ConsentLog::query()->where('form', 'client-account')->count())->toBe(1);
});

test('an admin confirms the client is informed when creating an account', function (): void {
    $payload = [
        'name' => 'Новый клиент',
        'email' => 'new-client@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.clients.store'), $payload)
        ->assertSessionHasErrors('consent');

    $this->actingAs(User::query()->where('role', 'admin')->first())
        ->post(route('admin.clients.store'), [...$payload, 'consent' => true])
        ->assertRedirect();

    $client = User::query()->where('email', 'new-client@example.com')->firstOrFail();
    $log = ConsentLog::query()->where('form', 'admin-client-create')->firstOrFail();

    expect($log->user_id)->toBe($client->id);
    expect($log->meta['confirmed_by'])->not->toBeNull();
});
