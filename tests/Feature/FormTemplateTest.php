<?php

declare(strict_types=1);

use App\Models\FormTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\FileUploadService;
use Database\Seeders\FormTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(FormTemplateSeeder::class);
    $this->client = User::factory()->client()->create();
    $this->admin = User::factory()->admin()->create();
});

test('client submits a structured bug report and it becomes a task', function (): void {
    $template = FormTemplate::query()->where('key', 'bug-report')->firstOrFail();

    $response = $this->actingAs($this->client)->post(route('client.tasks.forms.store', $template->key), [
        'consent' => true,
        'title' => 'Не работает вход',
        'priority' => 'high',
        'form_data' => [
            'fields' => [
                'severity' => 'high',
                'what_happened' => 'Кнопка входа не реагирует.',
                'expected' => 'Открывается личный кабинет.',
                'actual' => 'Страница остаётся на месте.',
                'environment' => 'Chrome, desktop',
            ],
            'groups' => [
                'steps' => [
                    ['step' => 'Открыть страницу входа', 'screenshot' => ''],
                    ['step' => 'Ввести пароль и нажать «Войти»', 'screenshot' => ''],
                ],
            ],
        ],
    ]);

    $task = Task::query()->where('form_template_id', $template->id)->firstOrFail();

    $response->assertRedirect(route('client.tasks.show', $task));
    expect($task->form_data['fields']['what_happened'])->toContain('Кнопка');
    expect($task->form_data['groups']['steps'])->toHaveCount(2);
    expect($task->form_template_id)->toBe($template->id);

    $this->actingAs($this->client)
        ->get(route('client.tasks.show', $task))
        ->assertOk();
});

test('required structured fields and repeatable steps are enforced', function (): void {
    $template = FormTemplate::query()->where('key', 'bug-report')->firstOrFail();

    $this->actingAs($this->client)
        ->post(route('client.tasks.forms.store', $template->key), [
            'consent' => true,
            'title' => 'Пустая заявка',
            'form_data' => ['fields' => [], 'groups' => []],
        ])
        ->assertSessionHasErrors([
            'form_data.fields.what_happened',
            'form_data.fields.expected',
            'form_data.fields.actual',
            'form_data.groups.steps',
        ]);

    expect(Task::query()->count())->toBe(0);
});

test('admin can open the forms library', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.forms.index'))
        ->assertOk();
});

test('form images can be previewed inline and are access controlled', function (): void {
    Storage::fake('local');

    $file = app(FileUploadService::class)->upload(
        UploadedFile::fake()->image('proof.png', 320, 180),
        $this->client,
    );

    $response = $this->actingAs($this->client)->get(route('files.preview', $file));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('inline');

    $other = User::factory()->client()->create();

    $this->actingAs($other)
        ->get(route('files.preview', $file))
        ->assertForbidden();
});
