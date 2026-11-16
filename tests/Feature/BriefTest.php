<?php

declare(strict_types=1);

use App\Enums\BriefStatus;
use App\Enums\TaskSource;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\Brief;
use App\Models\BriefTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\CrmHelpService;
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

test('Kiki creates a brief template from a spoken description', function (): void {
    $toolbox = app(CrmToolbox::class);

    $result = $toolbox->execute('create_brief_template', $this->admin, [
        'title' => 'Аудит рекламы',
        'summary' => 'Собираем доступы и цели по рекламным кабинетам',
        'sections' => [
            [
                'title' => 'Доступы',
                'fields' => [
                    ['label' => 'Кабинеты', 'type' => 'multi_choice', 'options' => [
                        ['value' => 'yandex', 'label' => 'Яндекс Директ'],
                        ['value' => 'vk', 'label' => 'VK Реклама'],
                    ]],
                    ['label' => 'Бюджет в месяц', 'type' => 'number', 'required' => true],
                ],
            ],
        ],
    ]);

    expect($result['slug'])->toBe('audit-reklamy')
        ->and($result['version'])->toBe(1)
        ->and($result['sections'])->toBe(1)
        ->and($result['fields'])->toBe(2);

    $template = BriefTemplate::query()->where('slug', 'audit-reklamy')->firstOrFail();

    expect($template->schema['sections'][0]['fields'][0]['key'])->toBe('kabinety')
        ->and($template->schema['sections'][0]['fields'][1]['required'])->toBeTrue();
});

test('Kiki rejects a template with an unknown field type', function (): void {
    $toolbox = app(CrmToolbox::class);

    expect(fn () => $toolbox->execute('create_brief_template', $this->admin, [
        'title' => 'Кривой шаблон',
        'sections' => [[
            'title' => 'Секция',
            'fields' => [['label' => 'Что-то', 'type' => 'wizard']],
        ]],
    ]))->toThrow(ValidationException::class);

    expect(BriefTemplate::query()->where('title', 'Кривой шаблон')->exists())->toBeFalse();
});

test('Kiki updates a template and bumps the schema version', function (): void {
    $toolbox = app(CrmToolbox::class);

    $result = $toolbox->execute('update_brief_template', $this->admin, [
        'template' => 'test-brief',
        'title' => 'Тестовый бриф v2',
        'sections' => [[
            'title' => 'Новая секция',
            'fields' => [['label' => 'Новое поле', 'type' => 'text', 'required' => true]],
        ]],
    ]);

    expect($result['version'])->toBe(2)
        ->and($result['sections'])->toBe(1);

    $template = $this->template->refresh();

    expect($template->title)->toBe('Тестовый бриф v2')
        ->and($template->schema_hash)->not->toBe(BriefSchema::hash(briefSchema()));
});

test('Kiki deletes an unused template and refuses a used one', function (): void {
    $toolbox = app(CrmToolbox::class);

    $unused = BriefTemplate::query()->create([
        'slug' => 'spare',
        'title' => 'Запасной',
        'schema' => BriefSchema::normalize(briefSchema()),
        'schema_hash' => BriefSchema::hash(briefSchema()),
        'version' => 1,
        'is_active' => true,
        'sort_order' => 50,
    ]);

    $toolbox->execute('delete_brief_template', $this->admin, ['template' => 'spare']);
    expect(BriefTemplate::query()->find($unused->id))->toBeNull();

    expect(fn () => $toolbox->execute('delete_brief_template', $this->admin, ['template' => 'test-brief']))
        ->toThrow(ValidationException::class);

    expect(BriefTemplate::query()->where('slug', 'test-brief')->exists())->toBeTrue();
});

test('brief template writes require confirmation except creation', function (): void {
    $toolbox = app(CrmToolbox::class);

    expect($toolbox->requiresConfirmation('create_brief_template'))->toBeFalse()
        ->and($toolbox->requiresConfirmation('update_brief_template'))->toBeTrue()
        ->and($toolbox->requiresConfirmation('delete_brief_template'))->toBeTrue();
});

test('admin removes an unused template from the panel and cannot remove a used one', function (): void {
    $unused = BriefTemplate::query()->create([
        'slug' => 'panel-spare',
        'title' => 'Панельный',
        'schema' => BriefSchema::normalize(briefSchema()),
        'schema_hash' => BriefSchema::hash(briefSchema()),
        'version' => 1,
        'is_active' => true,
        'sort_order' => 60,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.briefs.templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Briefs/Templates')->has('templates', 2));

    $this->actingAs($this->admin)
        ->delete(route('admin.briefs.templates.destroy', $unused))
        ->assertRedirect();

    expect(BriefTemplate::query()->find($unused->id))->toBeNull();

    $this->actingAs($this->admin)
        ->delete(route('admin.briefs.templates.destroy', $this->template))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(BriefTemplate::query()->where('slug', 'test-brief')->exists())->toBeTrue();
});

test('a client cannot touch the template library', function (): void {
    $this->actingAs($this->client)
        ->get(route('admin.briefs.templates'))
        ->assertForbidden();

    $this->actingAs($this->client)
        ->delete(route('admin.briefs.templates.destroy', $this->template))
        ->assertForbidden();
});

test('Kiki proposes deleting a template and the confirmation removes it', function (): void {
    config([
        'ai.kernel_provider_token' => 'test-provider-token',
        'ai.kernel_channel' => 'crm',
    ]);

    $template = BriefTemplate::query()->create([
        'slug' => 'chat-spare',
        'title' => 'Чат-запасной',
        'schema' => BriefSchema::normalize(briefSchema()),
        'schema_hash' => BriefSchema::hash(briefSchema()),
        'version' => 1,
        'is_active' => true,
        'sort_order' => 70,
    ]);

    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Шаблоны',
        'last_message_at' => now(),
    ]);

    $headers = ['Authorization' => 'Bearer test-provider-token', 'X-Kernel-Channel' => 'crm'];

    $proposal = $this->withHeaders($headers)
        ->postJson('/api/internal/assistant/tools/delete_brief_template', [
            'conversation_id' => $conversation->id,
            'actor' => ['id' => $this->admin->id, 'role' => 'admin'],
            'arguments' => ['template' => 'chat-spare'],
        ])
        ->assertOk()
        ->assertJsonPath('result.status', 'awaiting_confirmation')
        ->assertJsonPath('pending_action.tool_name', 'delete_brief_template');

    $action = AiAction::query()->findOrFail($proposal->json('pending_action.id'));

    $this->actingAs($this->admin)
        ->postJson(route('assistant.confirm', $action))
        ->assertOk();

    expect(BriefTemplate::query()->find($template->id))->toBeNull()
        ->and($action->refresh()->status->value)->toBe('executed');
});

test('Kiki creates a brief for a task and resolves client and project from it', function (): void {
    $task = Task::query()->create([
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'created_by' => $this->admin->id,
        'title' => 'Корейский проект',
        'description' => 'Нужен сайт и бриф по вводным',
        'status' => 'queued',
        'priority' => 'medium',
        'type' => 'one_time',
    ]);

    $toolbox = app(CrmToolbox::class);

    $templates = $toolbox->execute('list_brief_templates', $this->admin, []);
    expect(collect($templates['templates'])->pluck('slug'))->toContain('test-brief');

    $result = $toolbox->execute('create_brief', $this->admin, [
        'template' => 'test-brief',
        'task_id' => $task->id,
        'title' => 'Бриф корейского проекта',
    ]);

    expect($result['title'])->toBe('Бриф корейского проекта')
        ->and($result['client'])->toBe($this->client->name)
        ->and($result['project'])->toBe('BRF');

    $brief = Brief::query()->findOrFail($result['id']);

    expect($brief->client_id)->toBe($this->client->id)
        ->and($brief->project_id)->toBe($this->project->id);
});

test('create_brief is confirmed and hidden from clients', function (): void {
    $toolbox = app(CrmToolbox::class);

    expect($toolbox->requiresConfirmation('create_brief'))->toBeTrue();

    $clientTools = array_column(array_column($toolbox->definitions($this->client), 'function'), 'name');
    expect($clientTools)->not->toContain('create_brief')->not->toContain('list_brief_templates');

    $adminTools = array_column(array_column($toolbox->definitions($this->admin), 'function'), 'name');
    expect($adminTools)->toContain('create_brief', 'list_brief_templates');
});

test('the CRM help map knows the briefs section', function (): void {
    $help = app(CrmHelpService::class);
    $catalog = collect($help->catalog());

    $adminBriefs = $catalog->firstWhere('id', 'admin.briefs.index');
    $clientBriefs = $catalog->firstWhere('id', 'client.briefs.index');
    $templates = $catalog->firstWhere('id', 'admin.briefs.templates');

    expect($adminBriefs)->not->toBeNull()
        ->and($adminBriefs['path'])->toBe('/admin/briefs')
        ->and($clientBriefs['path'])->toBe('/briefs')
        ->and($templates['path'])->toBe('/admin/briefs/templates')
        ->and($templates['roles'])->toContain('admin');
});

test('Kiki proposes creating a brief and confirms it into the client portal', function (): void {
    config([
        'ai.kernel_provider_token' => 'test-provider-token',
        'ai.kernel_channel' => 'crm',
    ]);

    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Бриф',
        'last_message_at' => now(),
    ]);

    $proposal = $this->withHeaders(['Authorization' => 'Bearer test-provider-token', 'X-Kernel-Channel' => 'crm'])
        ->postJson('/api/internal/assistant/tools/create_brief', [
            'conversation_id' => $conversation->id,
            'actor' => ['id' => $this->admin->id, 'role' => 'admin'],
            'arguments' => [
                'template' => 'test-brief',
                'client_id' => $this->client->id,
                'title' => 'Бриф из чата',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.status', 'awaiting_confirmation');

    $action = AiAction::query()->findOrFail($proposal->json('pending_action.id'));

    $this->actingAs($this->admin)
        ->postJson(route('assistant.confirm', $action))
        ->assertOk();

    expect(Brief::query()->where('title', 'Бриф из чата')->exists())->toBeTrue();
});

test('create_brief accepts a template slug and rejects unknown identifiers cleanly', function (): void {
    $toolbox = app(CrmToolbox::class);

    $brief = $toolbox->execute('create_brief', $this->admin, [
        'template' => 'test-brief',
        'client_id' => $this->client->id,
        'title' => 'Бриф по slug',
    ]);

    expect($brief['template'])->toBe('Тестовый бриф');

    expect(fn () => $toolbox->execute('create_brief', $this->admin, ['template' => 'no-such-template']))
        ->toThrow(ValidationException::class);

    expect(fn () => $toolbox->execute('create_brief', $this->admin, [
        'template' => 'test-brief',
        'client_id' => 'not-a-uuid',
    ]))->toThrow(ValidationException::class);
});

test('admin renames a brief and the title reaches the client view', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('admin.briefs.update', $this->brief), ['title' => '  Новое название брифа  '])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->brief->refresh()->title)->toBe('Новое название брифа');

    $this->actingAs($this->client)
        ->get(route('client.briefs.show', $this->brief))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('brief.title', 'Новое название брифа'));
});

test('an empty title is rejected and the old one survives', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('admin.briefs.update', $this->brief), ['title' => '   '])
        ->assertSessionHasErrors('title');

    expect($this->brief->refresh()->title)->toBe('Бриф — тест');
});

test('a title longer than 200 characters is rejected', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('admin.briefs.update', $this->brief), ['title' => str_repeat('я', 201)])
        ->assertSessionHasErrors('title');
});

test('a client cannot rename a brief', function (): void {
    $this->actingAs($this->client)
        ->patch(route('admin.briefs.update', $this->brief), ['title' => 'Взлом'])
        ->assertForbidden();

    expect($this->brief->refresh()->title)->not->toBe('Взлом');
});

test('Kiki renames a brief and clients cannot call the tool', function (): void {
    $toolbox = app(CrmToolbox::class);

    $result = $toolbox->execute('update_brief', $this->admin, [
        'brief_id' => $this->brief->id,
        'title' => 'Бриф из чата',
    ]);

    expect($result['title'])->toBe('Бриф из чата')
        ->and($this->brief->refresh()->title)->toBe('Бриф из чата');

    expect(fn () => $toolbox->execute('update_brief', $this->client, [
        'brief_id' => $this->brief->id,
        'title' => 'Взлом',
    ]))->toThrow(ValidationException::class);
});

test('MCP renames a brief', function (): void {
    $this->withHeaders([
        'Authorization' => 'Bearer '.config('mcp.token'),
        'Content-Type' => 'application/json',
    ])->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'update_brief',
            'arguments' => ['brief' => $this->brief->id, 'title' => 'Из MCP'],
        ],
    ])->assertOk();

    expect($this->brief->refresh()->title)->toBe('Из MCP');
});
