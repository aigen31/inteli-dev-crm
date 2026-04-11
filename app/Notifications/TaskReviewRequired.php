<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReviewRequired extends Notification
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
            ->subject('Ожидается ваша оценка работы')
            ->line("Работа по задаче «{$this->task->title}» выполнена.")
            ->line('Пожалуйста, оцените результат и примите или отклоните работу.')
            ->action('Оценить работу', url("/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'review_required',
            'task_id' => $this->task->id,
            'message' => "Ожидается оценка: «{$this->task->title}»",
        ];
    }
}
