<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceCreated;
use Illuminate\Console\Command;

class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Отправить напоминания по неоплаченным счетам старше 3 дней';

    public function handle(): int
    {
        $invoices = Invoice::where('status', InvoiceStatus::Pending)
            ->where('created_at', '<=', now()->subDays(3))
            ->with('client', 'task')
            ->get();

        $this->info("Неоплаченных счетов для напоминания: {$invoices->count()}");

        foreach ($invoices as $invoice) {
            $invoice->client->notify(new InvoiceCreated($invoice));
            $this->line("  → Напоминание отправлено: {$invoice->client->email} (счёт #{$invoice->id})");
        }

        return self::SUCCESS;
    }
}
