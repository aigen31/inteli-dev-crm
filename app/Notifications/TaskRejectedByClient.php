<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskRejectedByClient extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Клиент отклонил работу')
            ->line("Клиент {$this->task->client->name} отклонил работу по задаче «{$this->task->title}».");

        if ($this->reason) {
            $mail->line("Причина: {$this->reason}");
        }

        return $mail->action('Просмотреть', url("/admin/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_rejected',
            'task_id' => $this->task->id,
            'message' => "Работа отклонена: «{$this->task->title}»",
            'reason' => $this->reason,
        ];
    }
}
