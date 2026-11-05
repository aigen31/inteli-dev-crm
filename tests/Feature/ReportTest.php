<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');

    config([
        'mcp.token' => 'test-mcp-token',
        'mcp.actor_id' => null,
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->recipient = User::factory()->client()->create();
    $this->other = User::factory()->client()->create();
});

test('an admin sends a standalone report with a test link and screenshot', function (): void {
    $this->actingAs($this->admin)->post(route('admin.reports.store'), [
        'recipient_id' => $this->recipient->id,
        'title' => 'Исправил форму заявки',
        'items' => [
            [
                'summary' => 'Починил форму заявки и проверил на мобильном.',
                'test_url' => 'https://example.com/contact',
                'screenshot' => UploadedFile::fake()->image('report.png', 800, 600),
            ],
            [
                'summary' => 'Проверил уведомления в CRM.',
            ],
        ],
    ])->assertRedirect();

    $report = TaskReport::query()->firstOrFail();

    expect($report->task_id)->toBeNull();
    expect($report->title)->toBe('Исправил форму заявки');
    expect($report->recipient_id)->toBe($this->recipient->id);
    expect($report->content)->toContain('Починил форму');
    expect($report->sent_at)->not->toBeNull();
    expect($report->items)->toHaveCount(2);
    expect($report->items[0]->test_url)->toBe('https://example.com/contact');
    expect($report->items[0]->screenshot->mime_type)->toBe('image/png');
    expect($report->items[1]->content)->toContain('уведомления');

    $this->actingAs($this->recipient)
        ->get(route('client.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Client/Reports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.summary', 'Починил форму заявки и проверил на мобильном.'));

    $this->actingAs($this->admin)
        ->get(route('reports.show', $report))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Show')
            ->where('report.id', $report->id)
            ->where('report.display_title', 'Исправил форму заявки')
            ->has('report.items', 2)
            ->where('report.recipient.email', $this->recipient->email));

    $this->actingAs($this->recipient)->get(route('reports.screenshot', $report))->assertOk();
    $this->actingAs($this->recipient)
        ->get(route('reports.screenshot', ['report' => $report, 'item' => $report->items[0]->id]))
        ->assertOk();
    $this->actingAs($this->recipient)
        ->get(route('reports.screenshot', ['report' => $report, 'item' => $report->items[1]->id]))
        ->assertNotFound();
    $this->actingAs($this->other)->get(route('reports.show', $report))->assertForbidden();
    $this->actingAs($this->other)->get(route('reports.screenshot', $report))->assertForbidden();
});

test('a report can be attached to a task or stand alone', function (): void {
    $task = Task::factory()->create([
        'client_id' => $this->recipient->id,
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->post(route('admin.reports.store'), [
        'task_id' => $task->id,
        'recipient_id' => $this->recipient->id,
        'items' => [[
            'summary' => 'Готово, можно проверять.',
            'test_url' => 'https://example.com/page',
        ]],
    ])->assertRedirect();

    $report = TaskReport::query()->firstOrFail();
    expect($report->task_id)->toBe($task->id);
    expect($report->screenshot_id)->toBeNull();
    expect($report->items)->toHaveCount(1);

    $this->actingAs($this->recipient)->get(route('reports.show', $report))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Client/Reports/Show')
            ->where('report.task.id', $task->id)
            ->where('report.summary', 'Готово, можно проверять.')
            ->has('report.items', 1));

    expect($report->refresh()->read_at)->not->toBeNull();

    $this->actingAs($this->recipient)->get(route('client.reports.index'))->assertOk();
});

test('the MCP server initializes lists tools and creates a report', function (): void {
    $headers = [
        'Authorization' => 'Bearer test-mcp-token',
        'Accept' => 'application/json, text/event-stream',
    ];

    $initialize = $this->withHeaders($headers)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => ['protocolVersion' => '2024-11-05', 'capabilities' => [], 'clientInfo' => ['name' => 'test']],
    ]);

    $initialize->assertOk()->assertJsonPath('result.serverInfo.name', 'inteli-dev-crm');

    $tools = $this->withHeaders($headers)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
    ]);

    $tools->assertOk();
    expect(collect($tools->json('result.tools'))->pluck('name'))->toContain('create_task_report');

    $call = $this->withHeaders($headers)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_task_report',
            'arguments' => [
                'title' => 'MCP-отчёт',
                'recipient_email' => $this->recipient->email,
                'items' => [
                    [
                        'summary' => 'MCP: собрал отчёт и приложил скриншот.',
                        'test_url' => 'https://example.com/mcp',
                        'screenshot_base64' => base64_encode(UploadedFile::fake()->image('mcp.png')->getContent()),
                        'screenshot_mime' => 'image/png',
                    ],
                    [
                        'summary' => 'MCP: вторая группа без скриншота.',
                    ],
                ],
            ],
        ],
    ]);

    $call->assertOk()->assertJsonPath('result.isError', false);
    expect($call->json('result.structuredContent.recipient.email'))->toBe($this->recipient->email);

    $report = TaskReport::query()->latest()->firstOrFail();
    expect($report->title)->toBe('MCP-отчёт');
    expect($report->content)->toContain('MCP');
    expect($report->screenshot_id)->not->toBeNull();
    expect($report->items)->toHaveCount(2);
    expect($report->recipient_id)->toBe($this->recipient->id);
});

test('the MCP endpoint rejects a wrong token', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer wrong'])->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});
