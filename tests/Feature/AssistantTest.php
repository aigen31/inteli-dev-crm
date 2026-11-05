<?php

declare(strict_types=1);

use App\Enums\AiActionStatus;
use App\Enums\AiMessageRole;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\FormTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\FileUploadService;
use Database\Seeders\FormTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(FormTemplateSeeder::class);

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
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function kernelChatPayload(string $conversationId, array $overrides = []): array
{
    return array_replace([
        'conversation_id' => $conversationId,
        'title' => 'Новый диалог',
        'answer' => 'Готово.',
        'message' => null,
        'messages' => [],
        'source' => 'kernel:local',
        'route' => 'local',
        'model' => 'local-model',
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        'pending_action' => null,
        'suggested_next' => [],
        'correlation_id' => 'test-correlation',
    ], $overrides);
}

test('chat stores an image attachment and forwards only its id to the kernel', function (): void {
    $attachment = app(FileUploadService::class)->upload(
        UploadedFile::fake()->image('screenshot.png', 640, 480),
        $this->client,
    );

    Http::fake([
        'kernel.test/v1/chat' => Http::response(kernelChatPayload('00000000-0000-0000-0000-000000000001', [
            'title' => 'Посмотри скриншот',
        ])),
    ]);

    $conversationId = '00000000-0000-0000-0000-000000000001';

    $response = $this->actingAs($this->client)->postJson(route('assistant.chat'), [
        'message' => 'Посмотри скриншот',
        'conversation_id' => $conversationId,
        'attachments' => [$attachment->id],
    ]);

    $response->assertOk();
    $response->assertJsonPath('session.title', 'Посмотри скриншот');

    Http::assertSent(function ($request) use ($attachment): bool {
        return str_ends_with($request->url(), '/v1/chat')
            && $request['attachments'][0]['id'] === $attachment->id
            && $request['attachments'][0]['mime'] === 'image/png';
    });

    $message = AiMessage::query()->where('role', AiMessageRole::User)->firstOrFail();
    expect($message->attachments)->toHaveCount(1);
    expect($message->attachments[0]['id'])->toBe($attachment->id);

    $conversation = AiConversation::query()->findOrFail($conversationId);
    expect($conversation->title)->toBe('Посмотри скриншот');
});

test('chat rejects a non-image attachment', function (): void {
    $attachment = app(FileUploadService::class)->upload(
        UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf'),
        $this->client,
    );

    $this->actingAs($this->client)->postJson(route('assistant.chat'), [
        'message' => 'Вот документ',
        'attachments' => [$attachment->id],
    ])->assertStatus(422);
});

test('an image-only message gets a readable default question', function (): void {
    $attachment = app(FileUploadService::class)->upload(
        UploadedFile::fake()->image('bug.png'),
        $this->client,
    );

    Http::fake([
        'kernel.test/v1/chat' => Http::response(kernelChatPayload('00000000-0000-0000-0000-000000000002')),
    ]);

    $this->actingAs($this->client)->postJson(route('assistant.chat'), [
        'message' => '',
        'attachments' => [$attachment->id],
    ])->assertOk();

    Http::assertSent(fn ($request): bool => ($request['message'] ?? null) === 'Что на изображении?');
});

test('the session list falls back to the local mirror when the kernel is down', function (): void {
    Http::fake(['kernel.test/*' => Http::response(['detail' => 'down'], 500)]);

    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Локальное зеркало',
        'last_message_at' => now(),
    ]);

    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'content' => 'Что сегодня в работе?',
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('assistant.conversations.index'));

    $response->assertOk();
    $response->assertJsonPath('sessions.0.id', $conversation->id);
    $response->assertJsonPath('sessions.0.title', 'Локальное зеркало');
    $response->assertJsonPath('sessions.0.source', 'local');
});

test('renaming a session updates the kernel and the mirror', function (): void {
    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Старое имя',
        'last_message_at' => now(),
    ]);

    Http::fake([
        'kernel.test/v1/conversations/*' => Http::response([
            'id' => $conversation->id,
            'title' => 'Новое имя',
            'pinned' => true,
            'archived' => false,
            'data_class' => 'internal',
            'message_count' => 0,
            'preview' => '',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]),
    ]);

    $this->actingAs($this->admin)
        ->patchJson(route('assistant.conversations.update', $conversation), ['title' => 'Новое имя', 'pinned' => true])
        ->assertOk()
        ->assertJsonPath('session.title', 'Новое имя');

    $conversation->refresh();
    expect($conversation->title)->toBe('Новое имя');
    expect($conversation->isPinned())->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && str_contains($request->url(), '/v1/conversations/'.$conversation->id)
        && str_contains($request->url(), 'actor_id='.$this->admin->id));
});

test('a session of another user stays invisible', function (): void {
    $conversation = AiConversation::query()->create([
        'user_id' => $this->client->id,
        'title' => 'Чужой диалог',
        'last_message_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('assistant.conversations.show', $conversation))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->deleteJson(route('assistant.conversations.destroy', $conversation))
        ->assertNotFound();
});

test('regenerate drops the trailing answer and asks the kernel again', function (): void {
    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Переспросить',
        'last_message_at' => now(),
    ]);

    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'content' => 'Что сегодня в работе?',
        'created_at' => now()->subMinute(),
    ]);
    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'content' => 'Старый ответ',
        'created_at' => now()->subSeconds(30),
    ]);

    Http::fake([
        'kernel.test/v1/conversations/*/regenerate' => Http::response(
            kernelChatPayload($conversation->id, ['answer' => 'Новый ответ']),
        ),
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('assistant.conversations.regenerate', $conversation))
        ->assertOk()
        ->assertJsonPath('message.content', 'Новый ответ');

    $contents = AiMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', AiMessageRole::Assistant)
        ->pluck('content')
        ->all();

    expect($contents)->toBe(['Новый ответ']);
});

test('a form proposal renders its schema and confirm creates a task with form data', function (): void {
    $conversation = AiConversation::query()->create([
        'user_id' => $this->client->id,
        'title' => 'Баг',
        'last_message_at' => now(),
    ]);

    $template = FormTemplate::query()->where('key', 'bug-report')->firstOrFail();

    $proposal = $this->withHeaders([
        'Authorization' => 'Bearer test-provider-token',
        'X-Kernel-Channel' => 'crm',
    ])->postJson('/api/internal/assistant/tools/create_task_from_form', [
        'conversation_id' => $conversation->id,
        'actor' => ['id' => $this->client->id, 'role' => 'client'],
        'arguments' => [
            'form_template_id' => $template->id,
            'title' => 'Кнопка входа не работает',
            'form_data' => [
                'fields' => [
                    'severity' => 'high',
                    'what_happened' => 'Кнопка не реагирует.',
                ],
            ],
        ],
    ]);

    $proposal->assertOk();
    $proposal->assertJsonPath('pending_action.type', 'form');
    $proposal->assertJsonPath('pending_action.form.template.key', 'bug-report');
    expect($proposal->json('pending_action.form.schema.fields'))->not->toBeEmpty();

    $action = AiAction::query()->findOrFail($proposal->json('pending_action.id'));
    expect($action->status)->toBe(AiActionStatus::Proposed);

    $this->actingAs($this->client)
        ->postJson(route('assistant.confirm', $action), [
            'arguments' => [
                'form_data' => [
                    'fields' => [
                        'severity' => 'high',
                        'what_happened' => 'Кнопка не реагирует.',
                        'expected' => 'Открывается кабинет.',
                        'actual' => 'Страница стоит на месте.',
                        'environment' => 'Chrome',
                    ],
                    'groups' => [
                        'steps' => [['step' => 'Нажать «Войти»', 'screenshot' => '']],
                    ],
                ],
            ],
        ])
        ->assertOk();

    $task = Task::query()->where('form_template_id', $template->id)->firstOrFail();

    expect($task->title)->toBe('Кнопка входа не работает');
    expect($task->form_data['fields']['expected'])->toBe('Открывается кабинет.');
    expect($task->form_data['groups']['steps'])->toHaveCount(1);
    expect($action->refresh()->status)->toBe(AiActionStatus::Executed);
});

test('the kernel file endpoint streams an attached image and refuses a foreign actor', function (): void {
    $attachment = app(FileUploadService::class)->upload(
        UploadedFile::fake()->image('screen.png'),
        $this->client,
    );

    $conversation = AiConversation::query()->create([
        'user_id' => $this->client->id,
        'title' => 'Скриншот',
        'last_message_at' => now(),
    ]);

    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'content' => 'Смотри',
        'attachments' => [['id' => $attachment->id]],
    ]);

    $headers = [
        'Authorization' => 'Bearer test-provider-token',
        'X-Kernel-Channel' => 'crm',
    ];

    $this->withHeaders($headers)
        ->get("/api/internal/assistant/files/{$attachment->id}?conversation_id={$conversation->id}&actor_id={$this->client->id}")
        ->assertOk()
        ->assertHeader('Content-Type', $attachment->mime_type);

    $this->withHeaders($headers)
        ->get("/api/internal/assistant/files/{$attachment->id}?conversation_id={$conversation->id}&actor_id={$this->admin->id}")
        ->assertForbidden();
});

test('the internal contract still requires the kernel service token', function (): void {
    $this->getJson('/api/internal/assistant/profile?actor_id='.$this->admin->id)
        ->assertUnauthorized();
});

test('an admin form proposal lists clients and needs one before confirming', function (): void {
    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Заявка для клиента',
        'last_message_at' => now(),
    ]);

    $template = FormTemplate::query()->where('key', 'bug-report')->firstOrFail();

    $proposal = $this->withHeaders([
        'Authorization' => 'Bearer test-provider-token',
        'X-Kernel-Channel' => 'crm',
    ])->postJson('/api/internal/assistant/tools/create_task_from_form', [
        'conversation_id' => $conversation->id,
        'actor' => ['id' => $this->admin->id, 'role' => 'admin'],
        'arguments' => [
            'form_template_id' => $template->id,
            'title' => 'Сломан вход',
            'form_data' => ['fields' => ['severity' => 'high']],
        ],
    ]);

    $proposal->assertOk();
    expect($proposal->json('pending_action.form.clients'))->toHaveCount(1);
    expect($proposal->json('pending_action.form.clients.0.id'))->toBe($this->client->id);

    $action = AiAction::query()->findOrFail($proposal->json('pending_action.id'));

    $this->actingAs($this->admin)
        ->postJson(route('assistant.confirm', $action), [
            'arguments' => [
                'form_data' => [
                    'fields' => [
                        'severity' => 'high',
                        'what_happened' => 'Кнопка не работает.',
                        'expected' => 'Кабинет.',
                        'actual' => 'Ничего.',
                    ],
                    'groups' => ['steps' => [['step' => 'Нажать', 'screenshot' => '']]],
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_id']);

    // The proposal survives a validation failure so the user can pick a client.
    expect($action->refresh()->status)->toBe(AiActionStatus::Proposed);
    expect(Task::query()->count())->toBe(0);

    $this->actingAs($this->admin)
        ->postJson(route('assistant.confirm', $action), [
            'arguments' => [
                'client_id' => $this->client->id,
                'form_data' => [
                    'fields' => [
                        'severity' => 'high',
                        'what_happened' => 'Кнопка не работает.',
                        'expected' => 'Кабинет.',
                        'actual' => 'Ничего.',
                    ],
                    'groups' => ['steps' => [['step' => 'Нажать', 'screenshot' => '']]],
                ],
            ],
        ])
        ->assertOk();

    expect(Task::query()->count())->toBe(1);
});

test('deleting a session removes the mirror and tells the kernel which actor owns it', function (): void {
    $conversation = AiConversation::query()->create([
        'user_id' => $this->admin->id,
        'title' => 'Удаляемый диалог',
        'last_message_at' => now(),
    ]);

    Http::fake(['kernel.test/*' => Http::response([], 204)]);

    $this->actingAs($this->admin)
        ->deleteJson(route('assistant.conversations.destroy', $conversation))
        ->assertOk();

    expect(AiConversation::query()->find($conversation->id))->toBeNull();

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/v1/conversations/'.$conversation->id)
        && str_contains($request->url(), 'actor_id='.$this->admin->id));
});
