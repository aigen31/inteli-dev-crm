<?php

declare(strict_types=1);

use App\Enums\BriefStatus;
use App\Enums\TaskSource;
use App\Models\Brief;
use App\Models\BriefTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\CrmToolbox;
use App\Services\Briefs\BriefSchema;
use App\Services\Briefs\BriefService;
use App\Services\Mcp\CrmMcpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Компактная схема: два поля, одно обязательное, одно условное.
 *
 * @return array<string, mixed>
 */
function briefSchema(): array
{
    return [
        'slug' => 'test-brief',
        'title' => 'Тестовый бриф',
        'description' => 'Схема для тестов',
        'sections' => [
            [
                'key' => 'company',
                'title' => 'О компании',
                'fields' => [
                    ['key' => 'site_url', 'type' => 'url', 'label' => 'Сайт', 'required' => true],
                    ['key' => 'has_cms', 'type' => 'single_choice', 'label' => 'Есть CMS?', 'options' => [
                        ['value' => 'yes', 'label' => 'Да'],
                        ['value' => 'no', 'label' => 'Нет'],
                    ]],
                    ['key' => 'cms_name', 'type' => 'text', 'label' => 'Какая CMS', 'show_if' => ['field' => 'has_cms', 'op' => 'eq', 'value' => 'yes']],
                ],
            ],
            [
                'key' => 'goals',
                'title' => 'Цели',
                'fields' => [
                    ['key' => 'goal', 'type' => 'textarea', 'label' => 'Цель', 'required' => true],
                ],
            ],
        ],
        'submit' => ['success_message' => 'Готово'],
    ];
}

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::query()->create([
        'key' => 'BRF',
        'name' => 'Brief project',
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'status' => 'active',
        'priority' => 'medium',
        'color' => 'violet',
    ]);

    $this->template = BriefTemplate::query()->create([
        'slug' => 'test-brief',
        'title' => 'Тестовый бриф',
        'summary' => 'Схема для тестов',
        'schema' => BriefSchema::normalize(briefSchema()),
        'schema_hash' => BriefSchema::hash(briefSchema()),
        'version' => 1,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $this->brief = app(BriefService::class)->create($this->template, [
        'title' => 'Бриф — тест',
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
    ], $this->admin);
});

test('admin creates a brief from a template', function (): void {
    $this->actingAs($this->admin)->post(route('admin.briefs.store'), [
        'brief_template_id' => $this->template->id,
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'title' => 'Новый бриф',
    ])->assertRedirect();

    $brief = Brief::query()->where('title', 'Новый бриф')->firstOrFail();

    expect($brief->status)->toBe(BriefStatus::Draft)
        ->and($brief->client_id)->toBe($this->client->id)
        ->and($brief->template_version)->toBe(1);
});

test('client sees own briefs and cannot open someone else’s', function (): void {
    $other = User::factory()->client()->create();

    $this->actingAs($this->client)
        ->get(route('client.briefs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Client/Briefs/Index')->has('briefs', 1));

    $this->actingAs($other)->get(route('client.briefs.show', $this->brief))->assertForbidden();
});

test('project member can open a brief of the project', function (): void {
    $member = User::factory()->client()->create();
    $this->project->members()->create([
        'user_id' => $member->id,
        'role' => 'viewer',
        'added_by' => $this->admin->id,
    ]);

    $this->actingAs($member)->get(route('client.briefs.show', $this->brief))->assertOk();
});

test('autosave stores answers, updates progress and switches status', function (): void {
    $response = $this->actingAs($this->client)->patchJson(route('client.briefs.save', $this->brief), [
        'answers' => ['site_url' => 'https://example.ru', 'has_cms' => 'yes', 'cms_name' => 'WordPress'],
    ]);

    $response->assertOk()->assertJsonStructure(['progress', 'status', 'saved_at']);

    $brief = $this->brief->refresh();

    expect($brief->answers()['site_url'])->toBe('https://example.ru')
        ->and($brief->status)->toBe(BriefStatus::InProgress)
        ->and($brief->progress)->toBe(50);
});

test('submitting without required answers returns field errors', function (): void {
    $this->actingAs($this->client)
        ->post(route('client.briefs.submit', $this->brief), ['answers' => ['goal' => '']])
        ->assertSessionHasErrors(['data.site_url', 'data.goal']);

    expect($this->brief->refresh()->status)->toBe(BriefStatus::Draft);
});

test('submitting a complete brief creates a revision and a review task', function (): void {
    $answers = ['site_url' => 'https://example.ru', 'goal' => 'Больше заявок'];

    $this->actingAs($this->client)
        ->post(route('client.briefs.submit', $this->brief), ['answers' => $answers])
        ->assertRedirect(route('client.briefs.show', $this->brief));

    $brief = $this->brief->refresh();

    expect($brief->status)->toBe(BriefStatus::Submitted)
        ->and($brief->progress)->toBe(100)
        ->and($brief->submitted_at)->not->toBeNull()
        ->and($brief->revisions)->toHaveCount(1)
        ->and($brief->schema_snapshot)->not->toBeNull();

    $task = Task::query()->where('form_data->brief_id', $brief->id)->first();

    expect($task)->not->toBeNull()
        ->and($task->source)->toBe(TaskSource::Brief)
        ->and($task->project_id)->toBe($this->project->id);
});

test('submitted brief is read-only for the client', function (): void {
    app(BriefService::class)->submit(
        app(BriefService::class)->saveAnswers($this->brief, ['site_url' => 'https://example.ru', 'goal' => 'Цель']),
        $this->client,
    );

    $this->actingAs($this->client)
        ->patchJson(route('client.briefs.save', $this->brief), ['answers' => ['goal' => 'Другое']])
        ->assertForbidden();
});

test('admin reopens a submitted brief for revision', function (): void {
    app(BriefService::class)->submit(
        app(BriefService::class)->saveAnswers($this->brief, ['site_url' => 'https://example.ru', 'goal' => 'Цель']),
        $this->client,
    );

    $this->actingAs($this->admin)
        ->post(route('admin.briefs.reopen', $this->brief))
        ->assertRedirect();

    expect($this->brief->refresh()->status)->toBe(BriefStatus::InProgress)
        ->and($this->brief->revisions()->count())->toBe(2);
});

test('admin archives a brief', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.briefs.archive', $this->brief))
        ->assertRedirect();

    expect($this->brief->refresh()->status)->toBe(BriefStatus::Archived)
        ->and($this->brief->archived_at)->not->toBeNull();
});

test('admin saves a note on the brief', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('admin.briefs.update', $this->brief), ['admin_note' => 'Созвониться в среду'])
        ->assertRedirect();

    expect($this->brief->refresh()->admin_note)->toBe('Созвониться в среду');
});

test('admin exports a brief as json, markdown and pdf', function (): void {
    app(BriefService::class)->submit(
        app(BriefService::class)->saveAnswers($this->brief, ['site_url' => 'https://example.ru', 'goal' => 'Цель']),
        $this->client,
    );

    $this->actingAs($this->admin)
        ->get(route('admin.briefs.export', [$this->brief, 'json']))
        ->assertOk()
        ->assertJsonPath('title', 'Бриф — тест');

    $markdown = $this->actingAs($this->admin)->get(route('admin.briefs.export', [$this->brief, 'md']));
    $markdown->assertOk();
    expect($markdown->getContent())->toContain('О компании')->toContain('https://example.ru');

    $pdf = $this->actingAs($this->admin)->get(route('admin.briefs.export', [$this->brief, 'pdf']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');
});

test('conditional field is hidden until its trigger is answered', function (): void {
    $schema = BriefSchema::normalize(briefSchema());
    $answers = ['has_cms' => 'no'];

    $sections = BriefSchema::sections($schema);
    $fields = BriefSchema::visibleFields($sections[0], $answers);
    $keys = array_column($fields, 'key');

    expect($keys)->toContain('has_cms')->not->toContain('cms_name');

    $answers['has_cms'] = 'yes';
    $keys = array_column(BriefSchema::visibleFields($sections[0], $answers), 'key');
    expect($keys)->toContain('cms_name');
});

test('progress counts only visible required fields', function (): void {
    $schema = BriefSchema::normalize(briefSchema());

    expect(BriefSchema::progress($schema, []))->toBe(0)
        ->and(BriefSchema::progress($schema, ['site_url' => 'https://a.ru']))->toBe(50)
        ->and(BriefSchema::progress($schema, ['site_url' => 'https://a.ru', 'goal' => 'x']))->toBe(100);
});

test('seed command loads templates from disk', function (): void {
    $this->artisan('briefs:seed')->assertSuccessful();

    expect(BriefTemplate::query()->where('slug', 'seo-audit')->exists())->toBeTrue()
        ->and(BriefTemplate::query()->where('slug', 'website-dev')->exists())->toBeTrue()
        ->and(BriefTemplate::query()->count())->toBeGreaterThanOrEqual(8);
});

test('MCP exposes brief tools and they read and write briefs', function (): void {
    config(['mcp.token' => 'test-mcp-token', 'mcp.actor_id' => $this->admin->id]);

    $headers = ['Authorization' => 'Bearer test-mcp-token', 'Accept' => 'application/json, text/event-stream'];

    $call = function (string $name, array $arguments) use ($headers): array {
        return $this->withHeaders($headers)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ])->assertOk()->json('result');
    };

    $tools = $this->withHeaders($headers)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk()->json('result.tools');

    expect(array_column($tools, 'name'))->toContain(
        'list_briefs',
        'get_brief',
        'create_brief',
        'reopen_brief',
        'archive_brief',
        'list_brief_templates',
    );

    $created = $call('create_brief', [
        'template' => 'test-brief',
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'title' => 'Бриф из MCP',
    ]);

    $briefId = $created['structuredContent']['id'] ?? $created['content'][0]['json']['id'] ?? null;
    expect($briefId)->not->toBeNull();

    app(BriefService::class)->saveAnswers(
        Brief::query()->findOrFail($briefId),
        ['site_url' => 'https://mcp.example', 'goal' => 'Заявки'],
    );

    $card = $call('get_brief', ['brief' => $briefId]);
    $payload = $card['structuredContent'] ?? json_decode($card['content'][0]['text'], true);
    expect($payload['sections'])->not->toBeEmpty();

    $list = $call('list_briefs', ['status' => 'open']);
    $listPayload = $list['structuredContent'] ?? json_decode($list['content'][0]['text'], true);
    expect(collect($listPayload['briefs'])->pluck('id'))->toContain($briefId);

    $this->expectException(ValidationException::class);
    app(CrmMcpService::class)->call('archive_brief', ['brief' => $briefId], $this->admin, request());
});

test('Kiki toolbox exposes briefs for the client', function (): void {
    config([
        'ai.kernel_provider_token' => 'test-provider-token',
        'ai.kernel_channel' => 'crm',
    ]);

    $headers = ['Authorization' => 'Bearer test-provider-token', 'X-Kernel-Channel' => 'crm'];

    $profile = $this->withHeaders($headers)
        ->getJson('/api/internal/assistant/profile?actor_id='.$this->client->id)
        ->assertOk();

    $names = array_column(array_column($profile->json('tools'), 'function'), 'name');
    expect($names)->toContain('list_briefs', 'get_brief');

    $toolbox = app(CrmToolbox::class);
    $result = $toolbox->execute('list_briefs', $this->client, ['status' => 'all']);
    expect(collect($result['briefs'])->pluck('id'))->toContain($this->brief->id);

    $card = $toolbox->execute('get_brief', $this->client, ['brief_id' => $this->brief->id]);
    expect($card['title'])->toBe('Бриф — тест');
});
