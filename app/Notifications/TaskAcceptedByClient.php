<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAcceptedByClient extends Notification
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
            ->subject('Клиент принял работу')
            ->line("Клиент {$this->task->client->name} принял работу по задаче «{$this->task->title}».")
            ->action('Просмотреть', url("/admin/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_accepted',
            'task_id' => $this->task->id,
            'message' => "Работа принята: «{$this->task->title}»",
        ];
    }
}
