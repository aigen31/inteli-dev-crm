<?php

declare(strict_types=1);

use App\Models\Brief;
use App\Models\BriefLink;
use App\Models\BriefTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\Briefs\BriefSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function publicBriefAnswers(): array
{
    return [
        'site_url' => 'https://koreatur.ru',
        'goal' => 'Больше заявок с сайта',
        'has_cms' => 'no',
    ];
}

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create(['name' => 'Иыан']);

    $this->template = BriefTemplate::query()->create([
        'slug' => 'public-brief',
        'title' => 'Публичный бриф',
        'schema' => BriefSchema::normalize(briefSchema()),
        'schema_hash' => BriefSchema::hash(briefSchema()),
        'version' => 1,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $this->brief = Brief::query()->create([
        'brief_template_id' => $this->template->id,
        'template_version' => 1,
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'title' => 'Бриф по ссылке',
        'status' => 'draft',
        'data' => [],
        'progress' => 0,
    ]);

    [$this->link, $this->token] = BriefLink::issue($this->brief, $this->admin, 7);
});

test('a guest opens a brief by link and the page is not indexable', function (): void {
    $response = $this->get(route('public.briefs.show', $this->token));

    $response->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertInertia(fn ($page) => $page
            ->component('Public/BriefFill')
            ->where('closed', false)
            ->where('brief.title', 'Бриф по ссылке'));
});

test('the token is stored as a hash, not as plain text', function (): void {
    $stored = BriefLink::query()->firstOrFail();

    expect($stored->token_hash)->not->toBe($this->token)
        ->and($stored->token_hash)->toBe(BriefLink::hash($this->token));
});

test('a guest autosaves answers through the public link', function (): void {
    $answers = ['site_url' => 'https://koreatur.ru', 'goal' => 'Сайт на корейском'];

    $this->patchJson(route('public.briefs.save', $this->token), ['answers' => $answers])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    expect($this->brief->refresh()->answers())->toMatchArray($answers);
});

test('a guest submits a complete brief and the link burns', function (): void {
    $this->brief->refresh();

    $answers = publicBriefAnswers();

    $this->post(route('public.briefs.submit', $this->token), ['answers' => $answers])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->brief->refresh();

    expect($this->brief->status->value)->toBe('submitted')
        ->and($this->brief->revisions()->where('reason', 'submitted_public')->exists())->toBeTrue()
        ->and(Task::query()->where('source', 'brief')->exists())->toBeTrue()
        ->and($this->link->refresh()->used_at)->not->toBeNull();

    $this->get(route('public.briefs.show', $this->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('closed', true)->where('closedReason', 'sent'));

    $this->patchJson(route('public.briefs.save', $this->token), ['answers' => $answers])->assertNotFound();
});

test('submit reports missing required fields instead of saving', function (): void {
    $this->post(route('public.briefs.submit', $this->token), ['answers' => []])
        ->assertSessionHasErrors('data.site_url');

    expect($this->brief->refresh()->status->value)->toBe('draft');
});

test('an expired link cannot save and shows a reason', function (): void {
    $this->link->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->patchJson(route('public.briefs.save', $this->token), ['answers' => ['site_url' => 'https://example.ru']])
        ->assertNotFound();

    $this->get(route('public.briefs.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('closed', true)->where('closedReason', 'expired'));
});

test('a revoked link is dead but explains itself', function (): void {
    $this->link->forceFill(['revoked_at' => now()])->save();

    $this->patchJson(route('public.briefs.save', $this->token), ['answers' => ['site_url' => 'https://example.ru']])
        ->assertNotFound();

    $this->get(route('public.briefs.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('closedReason', 'revoked'));
});

test('an unknown token is a 404', function (): void {
    $this->get(route('public.briefs.show', str_repeat('a', 48)))->assertNotFound();
});

test('archiving a brief revokes its links', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.briefs.archive', $this->brief))
        ->assertRedirect();

    expect($this->link->refresh()->revoked_at)->not->toBeNull();
});

test('an admin issues a link and only sees the URL once', function (): void {
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.briefs.links.store', $this->brief), ['days' => 3])
        ->assertOk()
        ->assertJsonPath('link.is_active', true);

    $url = $response->json('url');

    expect($url)->toContain('/b/')
        ->and(BriefLink::query()->where('brief_id', $this->brief->id)->count())->toBe(2);

    // Токен из URL в базе не лежит.
    $token = basename(parse_url($url, PHP_URL_PATH));
    expect(BriefLink::query()->where('token_hash', $token)->exists())->toBeFalse();
});

test('a client cannot issue a link', function (): void {
    $this->actingAs($this->client)
        ->postJson(route('admin.briefs.links.store', $this->brief), ['days' => 3])
        ->assertForbidden();
});

test('a submitted brief does not accept new links', function (): void {
    $this->brief->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();

    $this->actingAs($this->admin)
        ->postJson(route('admin.briefs.links.store', $this->brief), ['days' => 3])
        ->assertStatus(409);
});
