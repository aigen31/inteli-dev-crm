<?php

declare(strict_types=1);

namespace App\Services\Briefs;

use App\Actions\Task\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Brief;
use App\Models\Task;
use App\Models\User;

/**
 * Отправленный бриф превращается в задачу на разбор — CRM-native замена
 * уведомлению в Telegram из inteli-brief.
 */
class CreateBriefTaskAction
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
    ) {}

    public function createFor(Brief $brief, User $author): ?Task
    {
        $existing = Task::query()
            ->where('form_data->brief_id', $brief->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $clientName = $brief->client?->name;

        $description = collect([
            $clientName !== null ? "Клиент: {$clientName}" : null,
            $brief->project !== null ? "Проект: {$brief->project->key} · {$brief->project->name}" : null,
            $brief->template !== null ? "Шаблон: {$brief->template->title}" : null,
            "Ответов заполнено: {$brief->progress}%",
            '',
            'Бриф в CRM: '.route('admin.briefs.show', $brief),
        ])->filter(static fn (?string $line): bool => $line !== null)->implode("\n");

        return $this->createTask->execute([
            'title' => 'Бриф: '.$brief->title,
            'description' => $description,
            'client_id' => $brief->client_id,
            'project_id' => $brief->project_id,
            'status' => TaskStatus::Queued,
            'priority' => TaskPriority::Medium,
            'type' => TaskType::OneTime,
            'source' => TaskSource::Brief,
            'form_data' => [
                'brief_id' => $brief->id,
                'brief_title' => $brief->title,
                'template' => $brief->template?->title,
            ],
        ], $author);
    }
}
