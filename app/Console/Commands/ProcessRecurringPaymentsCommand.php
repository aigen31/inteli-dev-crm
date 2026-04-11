<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\ProcessRecurringPaymentAction;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class ProcessRecurringPaymentsCommand extends Command
{
    protected $signature = 'payments:process-recurring';

    protected $description = 'Обработка рекуррентных платежей, наступивших к оплате';

    public function handle(InvoiceService $invoiceService, ProcessRecurringPaymentAction $action): int
    {
        $invoices = $invoiceService->getDueRecurringInvoices();

        $this->info("Найдено рекуррентных счетов к оплате: {$invoices->count()}");

        foreach ($invoices as $invoice) {
            try {
                $action->execute($invoice);
                $this->line("  ✓ Счёт #{$invoice->id} — платёж создан");
            } catch (\Throwable $e) {
                $this->error("  ✗ Счёт #{$invoice->id} — ошибка: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
