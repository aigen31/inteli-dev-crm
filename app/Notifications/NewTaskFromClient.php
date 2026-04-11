<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTaskFromClient extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Новая заявка от клиента')
            ->line("Клиент {$this->task->client->name} создал новую заявку.")
            ->line("Задача: {$this->task->title}")
            ->action('Просмотреть', url("/admin/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_task',
            'task_id' => $this->task->id,
            'message' => "Новая заявка: {$this->task->title}",
        ];
    }
}
