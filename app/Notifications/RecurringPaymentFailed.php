<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecurringPaymentFailed extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Invoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Автоплатёж не прошёл')
            ->line("Не удалось списать автоплатёж на сумму {$this->invoice->amount} {$this->invoice->currency}.")
            ->line("Клиент: {$this->invoice->client->name}")
            ->action('Просмотреть', url("/admin/invoices/{$this->invoice->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'recurring_payment_failed',
            'invoice_id' => $this->invoice->id,
            'message' => "Автоплатёж не прошёл: {$this->invoice->amount} {$this->invoice->currency}",
        ];
    }
}
