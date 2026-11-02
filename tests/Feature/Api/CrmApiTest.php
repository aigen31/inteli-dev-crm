<?php

declare(strict_types=1);

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Ai\TemplateGenerator;
use App\Services\ApiTokenService;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function apiToken(User $user, array $abilities = ['read', 'write']): string
{
    return app(ApiTokenService::class)->create($user, 'test', $abilities)['plain'];
}

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->client = User::factory()->client()->create();
});

test('api requires a bearer token', function (): void {
    $this->getJson('/api/v1/clients')->assertUnauthorized();
    $this->getJson('/api/v1/clients', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
});

test('client token sees only its own client record while admin sees all', function (): void {
    $other = User::factory()->client()->create();

    $clientToken = apiToken($this->client, ['read']);
    $adminToken = apiToken($this->admin, ['read']);

    $this->withHeader('Authorization', 'Bearer '.$clientToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->client->id);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($other->id)->not->toBe($this->client->id);
});

test('read-only token cannot create a task', function (): void {
    $token = apiToken($this->admin, ['read']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/tasks', ['title' => 'Should fail'])
        ->assertForbidden()
        ->assertJsonPath('code', 'api_ability_denied');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/assistant/chat', ['message' => 'Привет'])
        ->assertForbidden();
});

test('custom field values are stored and exposed through the api', function (): void {
    CustomField::create([
        'entity_type' => CustomFieldEntity::Client,
        'key' => 'intake_status',
        'label' => 'Статус приёма заявок',
        'type' => CustomFieldType::Select,
        'options' => [
            ['value' => 'open', 'label' => 'Принимаю заявки'],
            ['value' => 'paused', 'label' => 'Пауза'],
        ],
        'default_value' => 'open',
        'is_required' => true,
        'is_active' => true,
        'show_in_list' => true,
        'created_by' => $this->admin->id,
    ]);

    $token = apiToken($this->admin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->putJson("/api/v1/custom-fields/client/{$this->client->id}/values", [
            'fields' => ['intake_status' => 'paused'],
        ])
        ->assertOk()
        ->assertJsonPath('data.fields.0.value', 'paused')
        ->assertJsonPath('data.fields.0.display', 'Пауза');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/custom-fields/client/{$this->client->id}/values")
        ->assertOk()
        ->assertJsonPath('data.fields.0.value', 'paused');

    $this->assertDatabaseHas('custom_field_values', [
        'entity_type' => 'client',
        'entity_id' => $this->client->id,
        'value_text' => 'paused',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonPath('data.0.custom_fields.intake_status.value', 'paused');
});

test('custom field defaults are exposed when no value is stored', function (): void {
    CustomField::create([
        'entity_type' => CustomFieldEntity::Client,
        'key' => 'intake_status',
        'label' => 'Статус приёма заявок',
        'type' => CustomFieldType::Select,
        'options' => [
            ['value' => 'open', 'label' => 'Принимаю заявки'],
            ['value' => 'paused', 'label' => 'Пауза'],
        ],
        'default_value' => 'open',
        'is_required' => true,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $token = apiToken($this->admin, ['read']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonPath('data.0.custom_fields.intake_status.value', 'open')
        ->assertJsonPath('data.0.custom_fields.intake_status.display', 'Принимаю заявки');
});

test('task template application creates a task with checklist and source', function (): void {
    $template = TaskTemplate::create([
        'name' => 'Лендинг под ключ',
        'summary' => 'Одностраничный сайт',
        'description' => '<p>Собираем лендинг</p><script>alert(1)</script>',
        'checklist' => [['text' => 'Бриф', 'done' => false], ['text' => 'Дизайн', 'done' => false]],
        'default_type' => 'one_time',
        'default_priority' => 'high',
        'default_status' => 'pending_review',
        'default_due_days' => 7,
        'estimated_minutes' => 600,
        'is_active' => true,
        'is_shared' => true,
        'created_by' => $this->admin->id,
    ]);

    $token = apiToken($this->admin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson("/api/v1/task-templates/{$template->id}/apply", [
            'client_id' => $this->client->id,
        ])
        ->assertCreated();

    $task = Task::query()->where('client_id', $this->client->id)->firstOrFail();

    expect($task->task_template_id)->toBe($template->id)
        ->and($task->source->value)->toBe('template')
        ->and($task->normalizedChecklist())->toHaveCount(2)
        ->and($task->deadline)->not->toBeNull();

    expect($template->refresh()->usage_count)->toBe(1);
});

test('client cannot read another clients task through the api', function (): void {
    $other = User::factory()->client()->create();
    $task = Task::factory()->create(['client_id' => $other->id, 'created_by' => $this->admin->id]);
    $token = apiToken($this->client, ['read']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertForbidden();
});

test('html sanitizer removes scripts and unsafe links', function (): void {
    $html = app(HtmlSanitizer::class)->sanitize(
        '<p>ok</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">bad</a><strong>bold</strong>',
    );

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('onclick')
        ->and($html)->toContain('<strong>bold</strong>');
});

test('template generator returns a deterministic draft', function (): void {
    $result = app(TemplateGenerator::class)->generate('Клиент просит настроить интеграцию с CRM и проверить ошибки');

    expect($result['source'])->toBe('draft')
        ->and($result['name'])->not->toBeEmpty()
        ->and($result['checklist'])->toHaveCount(4);
});


test('client token cannot see another clients invoice', function (): void {
    $other = User::factory()->client()->create();
    $task = Task::factory()->create(['client_id' => $other->id, 'created_by' => $this->admin->id]);
    $invoice = Invoice::factory()->create(['task_id' => $task->id, 'client_id' => $other->id]);
    $token = apiToken($this->client, ['read']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertForbidden();
});
