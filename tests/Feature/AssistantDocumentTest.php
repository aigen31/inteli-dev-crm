<?php

declare(strict_types=1);

use App\Enums\AiActionStatus;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiDocument;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\ProjectContext;
use App\Services\Ai\PromptFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');

    config([
        'ai.kernel_url' => 'http://kernel.test',
        'ai.kernel_token' => 'test-token',
        'ai.kernel_provider_token' => 'test-provider-token',
        'ai.kernel_channel' => 'crm',
        'ai.kernel_data_class' => 'internal',
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create();
});

/**
 * Minimal XLSX with a shared-strings table: enough for the extractor and
 * representative of a brief exported from a spreadsheet.
 */
function xlsxBrief(string $name = 'brief.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'kiki-xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(
        'xl/sharedStrings.xml',
        '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<si><t>Собрать список правок по дизайну</t></si><si><t>Обновить меню и разделы</t></si></sst>',
    );
    $zip->addFromString(
        'xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetData><row r="1"><c r="A1" t="s"><v>0</v></c></row><row r="2"><c r="A2" t="s"><v>1</v></c></row></sheetData></worksheet>',
    );
    $zip->close();

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function providerHeaders(): array
{
    return [
        'Authorization' => 'Bearer test-provider-token',
        'X-Kernel-Channel' => 'crm',
    ];
}

test('an xlsx brief is extracted once and exposed as a document', function (): void {
    $response = $this->actingAs($this->admin)->postJson(route('assistant.documents.store'), [
        'file' => xlsxBrief(),
    ]);

    $response->assertCreated();
    $response->assertJsonPath('name', 'brief.xlsx');
    expect($response->json('chars'))->toBeGreaterThan(20);
    expect($response->json('preview'))->toContain('Собрать список правок');

    $document = AiDocument::query()->firstOrFail();
    expect($document->text)->toContain('Обновить меню');
    expect($document->user_id)->toBe($this->admin->id);
});

test('read_document returns a slice and searches by words', function (): void {
    $this->actingAs($this->admin)->postJson(route('assistant.documents.store'), ['file' => xlsxBrief()]);
    $document = AiDocument::query()->firstOrFail();
    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Бриф',
        'last_message_at' => now(),
    ]);

    $slice = $this->withHeaders(providerHeaders())->postJson('/api/internal/assistant/tools/read_document', [
        'conversation_id' => $conversation->id,
        'actor' => ['id' => $this->admin->id, 'role' => 'admin'],
        'arguments' => ['document_id' => $document->id],
    ]);

    $slice->assertOk();
    expect($slice->json('result.text'))->toContain('Собрать список правок');

    $search = $this->withHeaders(providerHeaders())->postJson('/api/internal/assistant/tools/read_document', [
        'conversation_id' => $conversation->id,
        'actor' => ['id' => $this->admin->id, 'role' => 'admin'],
        'arguments' => ['document_id' => $document->id, 'query' => 'меню'],
    ]);

    $search->assertOk();
    expect($search->json('result.matches'))->toBeGreaterThan(0);
    expect($search->json('result.text'))->toContain('Обновить меню');
});

test('a plan proposal becomes several tasks with checklists and priority', function (): void {
    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'План из документа',
        'last_message_at' => now(),
    ]);

    $project = Project::query()->create([
        'key' => 'KOR',
        'name' => 'Koreatur',
        'status' => 'active',
        'priority' => 'high',
        'color' => 'violet',
        'client_id' => $this->client->id,
    ]);

    $proposal = $this->withHeaders(providerHeaders())->postJson('/api/internal/assistant/tools/propose_task_plan', [
        'conversation_id' => $conversation->id,
        'actor' => ['id' => $this->admin->id, 'role' => 'admin'],
        'arguments' => [
            'summary' => 'Редизайн сайта: 2 задачи',
            'project_id' => $project->id,
            'approach' => 'free',
            'tasks' => [
                [
                    'title' => 'Собрать список правок по дизайну',
                    'description' => 'Пройди по страницам и зафиксируй правки.',
                    'checklist' => ['Главная', 'Карточка тура'],
                    'priority' => 'high',
                    'due_days' => 3,
                ],
                [
                    'title' => 'Обновить меню и разделы',
                    'description' => 'Переработать структуру навигации.',
                    'priority' => 'medium',
                ],
            ],
        ],
    ]);

    $proposal->assertOk();
    $proposal->assertJsonPath('pending_action.type', 'plan');
    expect($proposal->json('pending_action.plan.tasks'))->toHaveCount(2);
    expect($proposal->json('pending_action.plan.clients'))->not->toBeEmpty();

    $action = AiAction::query()->findOrFail($proposal->json('pending_action.id'));

    $confirm = $this->actingAs($this->admin)->postJson(route('assistant.confirm', $action), [
        'arguments' => [
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'summary' => 'Редизайн сайта: 2 задачи',
            'tasks' => $proposal->json('pending_action.plan.tasks'),
        ],
    ]);

    $confirm->assertOk();
    expect(Task::query()->count())->toBe(2);

    $task = Task::query()->where('title', 'Собрать список правок по дизайну')->firstOrFail();
    expect($task->checklist)->toHaveCount(2);
    expect($task->priority->value)->toBe('high');
    expect($task->client_id)->toBe($this->client->id);
    expect($task->project_id)->toBe($project->id);
    expect($task->number)->toBe(1);
    expect($task->reference())->toBe('KOR-1');
    expect($action->refresh()->status)->toBe(AiActionStatus::Executed);
});

test('the assistant trace is mirrored on the stored message', function (): void {
    Http::fake([
        'kernel.test/v1/chat' => Http::response([
            'conversation_id' => '00000000-0000-0000-0000-00000000000a',
            'title' => 'Разбор брифа',
            'answer' => 'Собрала план.',
            'message' => null,
            'messages' => [],
            'source' => 'kernel:cloud',
            'route' => 'cloud',
            'model' => 'deepseek-flash',
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            'pending_action' => null,
            'suggested_next' => [],
            'trace' => [
                ['type' => 'tool', 'name' => 'read_document', 'status' => 'ok', 'summary' => '12000', 'duration_ms' => 12],
            ],
            'correlation_id' => 'corr',
        ]),
    ]);

    $this->actingAs($this->admin)->postJson(route('assistant.chat'), [
        'message' => 'Разбери документ',
        'conversation_id' => '00000000-0000-0000-0000-00000000000a',
    ])->assertOk();

    $message = AiMessage::query()
        ->where('role', 'assistant')
        ->latest()
        ->firstOrFail();

    expect($message->trace)->toHaveCount(1);
    expect($message->trace[0]['name'])->toBe('read_document');
});

test('project context feeds the assistant prompt', function (): void {
    $project = Project::query()->create([
        'key' => 'KOR',
        'name' => 'Koreatur',
        'status' => 'active',
        'priority' => 'high',
        'color' => 'violet',
        'site_url' => 'https://example.com',
        'agents_md' => "# Проект\n\n## Правила\n- Всегда проверять цены.",
        'instructions' => 'Не менять цены без согласования.',
        'client_id' => $this->client->id,
    ]);

    $context = app(ProjectContext::class);
    expect($context->promptFor($project))->toContain('проверять цены');
    expect($context->promptFor($project))->toContain('https://example.com');

    $system = app(PromptFactory::class)->system($this->admin, $project);
    expect($system)->toContain('Инструкция проекта');
    expect($system)->toContain('проверять цены');

    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'project_id' => $project->id,
        'title' => 'Koreatur',
        'last_message_at' => now(),
    ]);

    $profile = $this->withHeaders(providerHeaders())->getJson(
        '/api/internal/assistant/profile?actor_id='.$this->admin->id.'&actor_role=admin&conversation_id='.$conversation->id,
    );

    $profile->assertOk();
    expect($profile->json('system'))->toContain('проверять цены');
    expect($profile->json('system'))->toContain('KOR');
});

test('the assistant can ask the kernel to study the project site', function (): void {
    $project = Project::query()->create([
        'key' => 'KOR',
        'name' => 'Koreatur',
        'status' => 'active',
        'priority' => 'high',
        'color' => 'violet',
        'site_url' => 'https://example.com',
    ]);

    Http::fake([
        'kernel.test/v1/studies' => Http::response([
            'id' => 'study-chat-1',
            'status' => 'running',
            'url' => 'https://example.com',
            'events' => [],
        ]),
    ]);

    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Изучи сайт',
        'project_id' => $project->id,
        'last_message_at' => now(),
    ]);

    $response = $this->withHeaders(providerHeaders())->postJson('/api/internal/assistant/tools/study_project_site', [
        'conversation_id' => $conversation->id,
        'actor' => ['id' => $this->admin->id, 'role' => 'admin'],
        'arguments' => ['focus' => 'услуги и цены', 'project_id' => $project->id],
    ]);

    $response->assertOk();
    $response->assertJsonPath('result.status', 'started');
    $response->assertJsonPath('result.study_id', 'study-chat-1');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/studies')
        && $request['url'] === 'https://example.com');

    expect($project->studies()->count())->toBe(1);
    expect($project->studies()->first()->kernel_study_id)->toBe('study-chat-1');
});

test('site study is started and polled through the kernel', function (): void {
    $project = Project::query()->create([
        'key' => 'KOR',
        'name' => 'Koreatur',
        'status' => 'active',
        'priority' => 'high',
        'color' => 'violet',
        'site_url' => 'https://example.com',
    ]);

    Http::fake([
        'kernel.test/v1/studies' => Http::response([
            'id' => 'study-1',
            'status' => 'running',
            'url' => 'https://example.com',
            'events' => [],
        ], 202),
        'kernel.test/v1/studies/study-1*' => Http::response([
            'id' => 'study-1',
            'status' => 'completed',
            'url' => 'https://example.com',
            'agents_md' => "# AGENTS.md\n\n## О проекте\nСайт-визитка.",
            'events' => [['type' => 'final', 'text' => 'готово']],
        ]),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.projects.study', $project), ['focus' => 'услуги и цены'])
        ->assertRedirect();

    $study = $project->studies()->firstOrFail();
    expect($study->kernel_study_id)->toBe('study-1');

    $this->actingAs($this->admin)
        ->getJson(route('admin.projects.study.status', [$project, $study]))
        ->assertOk()
        ->assertJsonPath('study.status', 'completed');

    expect($project->refresh()->agents_md)->toContain('Сайт-визитка');
    expect($project->studied_at)->not->toBeNull();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/studies/study-1')
        && $request->method() === 'GET');
});

test('projects own numbered tasks and expose progress', function (): void {
    $store = $this->actingAs($this->admin)->post(route('admin.projects.store'), [
        'name' => 'Koreatur',
        'key' => 'KOR',
        'status' => 'active',
        'priority' => 'high',
        'color' => 'violet',
        'site_url' => 'https://example.com',
        'client_id' => $this->client->id,
    ]);
    $store->assertRedirect();

    $project = Project::query()->where('key', 'KOR')->firstOrFail();

    foreach (['Первый шаг', 'Второй шаг'] as $title) {
        $this->actingAs($this->admin)->post(route('admin.tasks.store'), [
            'client_id' => $this->client->id,
            'project_id' => $project->id,
            'title' => $title,
            'description' => 'Описание '.$title,
            'type' => 'one_time',
            'priority' => 'medium',
            'consent' => true,
        ])->assertRedirect();
    }

    $tasks = $project->tasks()->orderBy('number')->get();
    expect($tasks)->toHaveCount(2);
    expect($tasks[0]->number)->toBe(1);
    expect($tasks[0]->reference())->toBe('KOR-1');
    expect($tasks[1]->number)->toBe(2);

    $this->actingAs($this->admin)
        ->get(route('admin.projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Projects/Show')
            ->where('project.key', 'KOR')
            ->has('tasks', 2));
});
