<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Invoice\InvoicePaid;
use App\Models\User;
use App\Notifications\PaymentReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentReceivedNotification implements ShouldQueue
{
    public function handle(InvoicePaid $event): void
    {
        $admin = User::where('role', 'admin')->first();
        $admin?->notify(new PaymentReceived($event->invoice));
    }
}
