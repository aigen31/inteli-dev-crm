<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Invoice\InvoiceCreated;
use App\Notifications\InvoiceCreated as InvoiceCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendInvoiceCreatedNotification implements ShouldQueue
{
    public function handle(InvoiceCreated $event): void
    {
        $event->invoice->client->notify(
            new InvoiceCreatedNotification($event->invoice)
        );
    }
}
