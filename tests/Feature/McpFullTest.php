<?php

declare(strict_types=1);

use App\Models\ConsentLog;
use App\Models\FileAttachment;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['mcp.token' => 'test-mcp-token']);
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create(['name' => 'MCP Client', 'email' => 'mcp-client@example.com']);
    config(['mcp.actor_id' => $this->admin->id]);
    $this->project = Project::create([
        'key' => 'MCP',
        'name' => 'MCP Project',
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
    ]);
    $this->headers = ['Authorization' => 'Bearer test-mcp-token', 'Accept' => 'application/json, text/event-stream'];
});

function mcpCall($test, string $name, array $arguments): array
{
    $response = $test->withHeaders($test->headers)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => $arguments],
    ])->assertOk();

    return $response->json('result');
}

test('MCP exposes the core CRM surface with annotations', function (): void {
    $response = $this->withHeaders($this->headers)->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk();

    $tools = collect($response->json('result.tools'));
    expect($tools->pluck('name'))->toContain('create_report', 'update_report', 'delete_report', 'create_task', 'create_client', 'create_invoice', 'crm_operation', 'list_projects', 'get_report', 'update_client', 'create_task_template', 'apply_task_template', 'list_custom_fields', 'set_custom_field_values')
        ->and($tools->firstWhere('name', 'create_report')['annotations']['readOnlyHint'])->toBeFalse()
        ->and($tools->firstWhere('name', 'update_report')['annotations']['readOnlyHint'])->toBeFalse()
        ->and($tools->firstWhere('name', 'delete_report')['annotations']['destructiveHint'])->toBeTrue()
        ->and($tools->firstWhere('name', 'list_tasks')['annotations']['readOnlyHint'])->toBeTrue();
});

test('MCP creates a report using description and link template', function (): void {
    $result = mcpCall($this, 'create_report', [
        'title' => 'Проверка формы',
        'recipient_email' => $this->client->email,
        'template' => 'description_link',
        'items' => [
            ['description' => 'Починил форму заявки.', 'link' => 'https://example.com/form'],
            ['description' => 'Проверил уведомления.'],
        ],
    ]);

    expect($result['isError'])->toBeFalse();
    expect($result['structuredContent']['items'][0]['description'])->toBe('Починил форму заявки.');
    expect($result['structuredContent']['items'][0]['link'])->toBe('https://example.com/form');
    expect($result['structuredContent']['url'])->toContain('/reports/');

    $report = TaskReport::query()->firstOrFail();
    expect($report->items)->toHaveCount(2);
    expect($report->items[0]->content)->toBe('Починил форму заявки.');
    expect($report->items[0]->test_url)->toBe('https://example.com/form');
});

test('MCP updates a report and deletes it only with confirmation', function (): void {
    $created = mcpCall($this, 'create_report', [
        'title' => 'Черновик отчёта',
        'recipient_email' => $this->client->email,
        'items' => [
            ['description' => 'Первая версия.', 'link' => 'https://example.com/old'],
        ],
    ]);
    expect($created['isError'])->toBeFalse();
    $id = $created['structuredContent']['id'];

    $updated = mcpCall($this, 'update_report', [
        'report' => $id,
        'title' => 'Обновлённый отчёт',
        'recipient_email' => $this->client->email,
        'items' => [
            ['description' => 'Новая версия.', 'link' => 'https://example.com/new'],
            ['description' => 'Вторая группа.'],
        ],
    ]);

    expect($updated['isError'])->toBeFalse()
        ->and($updated['structuredContent']['updated'])->toBeTrue()
        ->and($updated['structuredContent']['items'])->toHaveCount(2)
        ->and($updated['structuredContent']['items'][0]['description'])->toBe('Новая версия.');

    $report = TaskReport::query()->findOrFail($id);
    expect($report->title)->toBe('Обновлённый отчёт')
        ->and($report->items)->toHaveCount(2)
        ->and($report->items[0]->test_url)->toBe('https://example.com/new');

    $denied = mcpCall($this, 'delete_report', ['report' => $id]);
    expect($denied['isError'])->toBeTrue();
    expect(TaskReport::query()->find($id))->not->toBeNull();

    $deleted = mcpCall($this, 'delete_report', ['report' => $id, 'confirm' => true]);
    expect($deleted['isError'])->toBeFalse()
        ->and($deleted['structuredContent']['deleted'])->toBeTrue();
    expect(TaskReport::query()->find($id))->toBeNull();
});

test('MCP creates clients only with explicit consent and logs it', function (): void {
    $denied = mcpCall($this, 'create_client', [
        'name' => 'No Consent',
        'email' => 'no-consent@example.com',
    ]);
    expect($denied['isError'])->toBeTrue();

    $result = mcpCall($this, 'create_client', [
        'name' => 'Consented Client',
        'email' => 'consented@example.com',
        'consent_confirmed' => true,
    ]);

    expect($result['isError'])->toBeFalse();
    $user = User::query()->where('email', 'consented@example.com')->firstOrFail();
    expect(ConsentLog::query()->where('user_id', $user->id)->where('form', 'mcp-client-create')->exists())->toBeTrue();
    expect($result['structuredContent']['temporary_password'])->not->toBeNull();
});

test('MCP creates tasks, comments, invoices and confirms a transfer payment', function (): void {
    $task = mcpCall($this, 'create_task', [
        'client_id' => $this->client->id,
        'project_id' => $this->project->id,
        'title' => 'MCP task',
        'description' => 'Task created through the MCP surface.',
        'priority' => 'high',
    ]);
    expect($task['isError'])->toBeFalse();
    $taskId = $task['structuredContent']['id'];
    expect(Task::query()->find($taskId)->project_id)->toBe($this->project->id);

    $comment = mcpCall($this, 'crm_operation', [
        'operation' => 'task.comment',
        'id' => $taskId,
        'payload' => ['content' => 'Комментарий через MCP.'],
    ]);
    expect($comment['isError'])->toBeFalse();

    $invoice = mcpCall($this, 'create_invoice', [
        'task_ids' => [$taskId],
        'amount' => 1000,
        'payment_method' => 'transfer',
        'confirm' => true,
    ]);
    expect($invoice['isError'])->toBeFalse();
    $invoiceId = $invoice['structuredContent']['id'];

    $paid = mcpCall($this, 'mark_invoice_paid', ['invoice' => $invoiceId, 'confirm' => true]);
    expect($paid['isError'])->toBeFalse();
    expect(Invoice::query()->find($invoiceId)->status->value)->toBe('paid');
});

test('MCP creates and updates a project through the generic operation', function (): void {
    $created = mcpCall($this, 'create_project', [
        'name' => 'Second MCP Project',
        'key' => 'MCP2',
        'client_id' => $this->client->id,
        'site_url' => 'https://example.com',
    ]);
    expect($created['isError'])->toBeFalse();
    $id = $created['structuredContent']['id'];

    $updated = mcpCall($this, 'crm_operation', [
        'operation' => 'project.update',
        'id' => $id,
        'payload' => ['name' => 'Updated MCP Project', 'site_url' => 'https://example.com/new'],
    ]);
    expect($updated['isError'])->toBeFalse();
    expect(Project::query()->find($id)->name)->toBe('Updated MCP Project');
});

test('MCP money actions refuse without confirmation', function (): void {
    $result = mcpCall($this, 'create_invoice', [
        'task_ids' => [Task::factory()->create(['client_id' => $this->client->id])->id],
        'payment_method' => 'transfer',
    ]);

    expect($result['isError'])->toBeTrue();
    expect($result['content'][0]['text'])->toContain('confirm=true');
});

test('MCP manages project members and creates an invite', function (): void {
    $member = User::factory()->client()->create();

    $added = mcpCall($this, 'crm_operation', [
        'operation' => 'project.member.add',
        'id' => $this->project->id,
        'payload' => ['user_id' => $member->id, 'role' => 'viewer'],
    ]);
    expect($added['isError'])->toBeFalse();
    expect($this->project->members()->where('user_id', $member->id)->first()?->role->value)->toBe('viewer');

    $invite = mcpCall($this, 'create_project_invite', [
        'project' => 'MCP',
        'role' => 'member',
        'days' => 7,
    ]);
    expect($invite['isError'])->toBeFalse();
    expect($invite['structuredContent']['url'])->toContain('/invite/');
});

test('MCP returns download URLs for screenshots attached to a task', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('shots/mcp.png', 'fake-png');

    $file = FileAttachment::query()->create([
        'uploaded_by' => $this->client->id,
        'original_name' => 'screenshot.png',
        'stored_path' => 'shots/mcp.png',
        'mime_type' => 'image/png',
        'size' => 8,
    ]);

    $task = Task::factory()->create([
        'client_id' => $this->client->id,
        'created_by' => $this->admin->id,
        'project_id' => $this->project->id,
        'title' => 'С формой и скриншотом',
        'form_data' => [
            'fields' => ['task_title' => 'С формой и скриншотом'],
            'groups' => ['items' => [['description' => 'Скриншот', 'image' => $file->id]]],
        ],
    ]);

    $result = mcpCall($this, 'get_task', ['task' => $task->id])['structuredContent'];

    expect($result['screenshots'])->toHaveCount(1)
        ->and($result['screenshots'][0]['id'])->toBe($file->id)
        ->and($result['form_data']['groups']['items'][0]['image'])->toBe($file->id);

    $url = $result['screenshots'][0]['url'];
    expect($url)->toContain('signature=');

    // Ссылка работает без сессии CRM — это и нужно внешнему агенту.
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');

    // Подпись нельзя подделать.
    $this->get($url.'tampered')->assertForbidden();
});

test('MCP returns signed screenshot links for report items', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('shots/report.png', 'fake-png');

    $file = FileAttachment::query()->create([
        'uploaded_by' => $this->admin->id,
        'original_name' => 'report.png',
        'stored_path' => 'shots/report.png',
        'mime_type' => 'image/png',
        'size' => 8,
    ]);

    $report = TaskReport::query()->create([
        'author_id' => $this->admin->id,
        'recipient_id' => $this->client->id,
        'title' => 'Отчёт со скриншотом',
        'content' => 'Проверка',
        'sent_at' => now(),
    ]);

    $report->items()->create([
        'content' => 'Проверьте страницу',
        'test_url' => 'https://example.ru',
        'screenshot_id' => $file->id,
    ]);

    $result = mcpCall($this, 'get_report', ['report' => $report->id])['structuredContent'];

    expect($result['items'][0]['screenshot_url'])->toContain('signature=');

    $this->get($result['items'][0]['screenshot_url'])->assertOk()->assertHeader('Content-Type', 'image/png');
});
