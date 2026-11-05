<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\Task\TaskCreated;
use App\Models\Task;
use App\Models\User;
use App\Services\HtmlSanitizer;

class CreateTaskAction
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function execute(array $data, User $creator): Task
    {
        $checklist = $this->normalizeChecklist($data['checklist'] ?? []);

        $projectId = $data['project_id'] ?? null;
        $projectId = $projectId !== null && $projectId !== '' ? (string) $projectId : null;

        $number = null;

        if ($projectId !== null) {
            $number = (int) Task::query()
                ->where('project_id', $projectId)
                ->max('number') + 1;
        }

        $task = Task::create([
            'client_id' => $data['client_id'] ?? $creator->id,
            'project_id' => $projectId,
            'number' => $number,
            'created_by' => $creator->id,
            'task_template_id' => $data['task_template_id'] ?? null,
            'form_template_id' => $data['form_template_id'] ?? null,
            'form_data' => $data['form_data'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'title' => $data['title'],
            'description' => $this->sanitizer->sanitize($data['description'] ?? ''),
            'checklist' => $checklist,
            'type' => $data['type'] ?? TaskType::OneTime,
            'source' => $data['source'] ?? ($creator->isClient() ? TaskSource::Client : TaskSource::Manual),
            'status' => $creator->isClient()
                ? TaskStatus::PendingReview
                : ($data['status'] ?? TaskStatus::PendingReview),
            'priority' => $data['priority'] ?? TaskPriority::Medium,
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'deadline' => $data['deadline'] ?? null,
        ]);

        TaskCreated::dispatch($task);

        return $task;
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
