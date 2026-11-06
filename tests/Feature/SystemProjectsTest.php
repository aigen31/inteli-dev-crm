<?php

declare(strict_types=1);

use App\Models\FeedbackItem;
use App\Models\FeedbackVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('all authenticated users can open both service projects', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();

    foreach ([$admin, $client] as $user) {
        foreach (['feedback', 'bugs'] as $project) {
            $this->actingAs($user)
                ->get(route('system-projects.show', ['project' => $project]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('SystemProjects/Show')->where('project.key', $project));
        }
    }
});

test('service projects are shown on the project index for both roles', function (): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();

    $this->actingAs($admin)->get(route('admin.projects.index'))
        ->assertInertia(fn (Assert $page) => $page->has('systemProjects', 2));

    $this->actingAs($client)->get(route('client.projects.index'))
        ->assertInertia(fn (Assert $page) => $page->has('systemProjects', 2));
});

test('a user can submit a feedback item and a bug report', function (): void {
    $user = User::factory()->client()->create();

    $this->actingAs($user)->post(route('system-projects.store', ['project' => 'feedback']), [
        'title' => 'Тёмная тема для таблиц',
        'description' => 'Хочу работать ночью без яркого белого фона.',
        'category' => 'improvement',
    ])->assertRedirect(route('system-projects.show', ['project' => 'feedback']));

    $this->actingAs($user)->post(route('system-projects.store', ['project' => 'bugs']), [
        'title' => 'Не отправляется форма',
        'description' => 'При отправке формы появляется ошибка.',
        'severity' => 'high',
        'steps' => 'Открыть форму и нажать отправить.',
        'expected' => 'Форма отправляется.',
        'actual' => 'Появляется ошибка 500.',
        'environment' => 'Chrome 150, macOS',
    ])->assertRedirect(route('system-projects.show', ['project' => 'bugs']));

    expect(FeedbackItem::query()->where('type', 'feedback')->count())->toBe(1)
        ->and(FeedbackItem::query()->where('type', 'bugs')->count())->toBe(1);
});

test('votes toggle once per user and count is public', function (): void {
    $user = User::factory()->client()->create();
    $item = FeedbackItem::query()->create([
        'user_id' => $user->id,
        'type' => 'feedback',
        'title' => 'Добавить экспорт',
        'description' => 'Нужен экспорт задач в CSV.',
        'category' => 'idea',
        'status' => 'open',
    ]);

    $route = route('system-projects.vote', ['project' => 'feedback', 'item' => $item]);

    $this->actingAs($user)->postJson($route)->assertOk()->assertJson(['votes' => 1, 'voted' => true]);
    $this->actingAs($user)->postJson($route)->assertOk()->assertJson(['votes' => 0, 'voted' => false]);
    expect(FeedbackVote::query()->count())->toBe(0);
});

test('only an admin can advance a service item status', function (): void {
    $owner = User::factory()->client()->create();
    $admin = User::factory()->admin()->create();
    $item = FeedbackItem::query()->create([
        'user_id' => $owner->id,
        'type' => 'bugs',
        'title' => 'Не сохраняется файл',
        'description' => 'После загрузки файл пропадает.',
        'severity' => 'high',
        'steps' => 'Загрузить файл и обновить страницу.',
        'expected' => 'Файл остаётся.',
        'actual' => 'Файл пропал.',
        'status' => 'open',
    ]);

    $route = route('system-projects.status', ['project' => 'bugs', 'item' => $item]);

    $this->actingAs($owner)->patch($route, ['status' => 'confirmed'])->assertForbidden();
    $this->actingAs($admin)->patch($route, ['status' => 'fixed'])->assertRedirect();

    expect($item->fresh()->status)->toBe('fixed')
        ->and($item->fresh()->resolved_at)->not->toBeNull();
});
