<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly TaskStatus $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Статус задачи изменён: {$this->newStatus->label()}")
            ->line("Статус задачи «{$this->task->title}» изменён на «{$this->newStatus->label()}».")
            ->action('Просмотреть задачу', url("/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_status_updated',
            'task_id' => $this->task->id,
            'message' => "Задача «{$this->task->title}»: {$this->newStatus->label()}",
        ];
    }
}
