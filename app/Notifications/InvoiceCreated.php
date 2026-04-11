<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceCreated extends Notification
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
            ->subject('Новый счёт на оплату')
            ->line("Вам выставлен счёт на сумму {$this->invoice->amount} {$this->invoice->currency}.")
            ->line("Описание: {$this->invoice->description}")
            ->action('Перейти к оплате', url("/invoices/{$this->invoice->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_created',
            'invoice_id' => $this->invoice->id,
            'message' => "Новый счёт: {$this->invoice->amount} {$this->invoice->currency}",
        ];
    }
}
