<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Task\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TaskTemplateService
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): TaskTemplate
    {
        return TaskTemplate::create([
            ...$this->normalize($data),
            'created_by' => $creator->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaskTemplate $template, array $data): TaskTemplate
    {
        $template->update($this->normalize($data));

        return $template->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function apply(
        TaskTemplate $template,
        User $client,
        User $creator,
        array $overrides = [],
        ?TaskSource $source = null,
    ): Task {
        if (! $template->is_active) {
            throw ValidationException::withMessages(['template' => 'Шаблон отключён.']);
        }

        if ($template->client_id !== null && $template->client_id !== $client->id) {
            throw ValidationException::withMessages(['template' => 'Шаблон недоступен этому клиенту.']);
        }

        $deadline = Arr::get($overrides, 'deadline');

        if ($deadline === null && $template->default_due_days !== null) {
            $deadline = now()->addDays($template->default_due_days)->toDateString();
        }

        $status = $creator->isClient()
            ? TaskStatus::PendingReview
            : ($template->default_status ?? TaskStatus::PendingReview);

        $task = $this->createTask->execute([
            'client_id' => $client->id,
            'title' => Arr::get($overrides, 'title') ?: $template->name,
            'description' => Arr::get($overrides, 'description') ?: $template->description,
            'type' => $template->default_type ?? TaskType::OneTime,
            'status' => $status,
            'priority' => $template->default_priority ?? TaskPriority::Medium,
            'deadline' => $deadline,
            'task_template_id' => $template->id,
            'assignee_id' => Arr::get($overrides, 'assignee_id') ?? $template->default_assignee_id,
            'checklist' => $template->normalizedChecklist(),
            'source' => $source ?? ($creator->isClient() ? TaskSource::Client : TaskSource::Template),
            'estimated_minutes' => $template->estimated_minutes,
        ], $creator);

        $template->increment('usage_count');

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $normalized = Arr::only($data, [
            'name',
            'summary',
            'checklist',
            'default_type',
            'default_priority',
            'default_status',
            'default_assignee_id',
            'default_due_days',
            'estimated_minutes',
            'client_id',
            'is_active',
            'is_shared',
        ]);

        $normalized['description'] = $this->sanitizer->sanitize($data['description'] ?? null);
        $normalized['checklist'] = $this->normalizeChecklist($data['checklist'] ?? []);

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array{text: string, done: bool}>
     */
    private function normalizeChecklist(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $text = trim($item);

                if ($text !== '') {
                    $normalized[] = ['text' => $text, 'done' => false];
                }

                continue;
            }

            if (is_array($item)) {
                $text = trim((string) ($item['text'] ?? ''));

                if ($text !== '') {
                    $normalized[] = ['text' => $text, 'done' => (bool) ($item['done'] ?? false)];
                }
            }
        }

        return $normalized;
    }
}
