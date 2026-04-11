<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
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
            ->subject('Платёж получен')
            ->line("Получен платёж на сумму {$this->invoice->amount} {$this->invoice->currency}.")
            ->line("Клиент: {$this->invoice->client->name}")
            ->line("Описание: {$this->invoice->description}")
            ->action('Просмотреть счёт', url("/admin/invoices/{$this->invoice->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_received',
            'invoice_id' => $this->invoice->id,
            'message' => "Платёж получен: {$this->invoice->amount} {$this->invoice->currency}",
        ];
    }
}
