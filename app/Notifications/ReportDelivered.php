<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TaskReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportDelivered extends Notification
{
    use Queueable;

    public function __construct(
        private readonly TaskReport $report,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->report->title ?: ($this->report->task_id !== null ? 'Отчёт по задаче' : 'Новый отчёт'));

        if (filled($this->report->title)) {
            $message->line($this->report->title);
        }

        $message->line($this->report->content);

        if (filled($this->report->test_url)) {
            $message->line('Страница для тестирования: '.$this->report->test_url);
        }

        return $message->action('Открыть отчёт', url('/reports/'.$this->report->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_report',
            'report_id' => $this->report->id,
            'task_id' => $this->report->task_id,
            'message' => mb_substr($this->report->content, 0, 160),
        ];
    }
}
