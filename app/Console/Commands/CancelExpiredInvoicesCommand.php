<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

class CancelExpiredInvoicesCommand extends Command
{
    protected $signature = 'invoices:cancel-expired';

    protected $description = 'Отменить неоплаченные счета старше 30 дней';

    public function handle(): int
    {
        $count = Invoice::where('status', InvoiceStatus::Pending)
            ->where('is_recurring', false)
            ->where('created_at', '<=', now()->subDays(30))
            ->update(['status' => InvoiceStatus::Cancelled]);

        $this->info("Отменено просроченных счетов: {$count}");

        return self::SUCCESS;
    }
}
