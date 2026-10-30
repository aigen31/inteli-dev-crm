<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurringInterval;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class InvoiceService
{
    public function calculateNextRecurringDate(RecurringInterval $interval, ?Carbon $from = null): Carbon
    {
        $from = $from ?? now();

        return match ($interval) {
            RecurringInterval::Monthly => $from->addMonth(),
            RecurringInterval::Quarterly => $from->addMonths(3),
            RecurringInterval::Yearly => $from->addYear(),
        };
    }

    public function getDueRecurringInvoices(): Collection
    {
        return Invoice::where('is_recurring', true)
            ->where('status', 'paid')
            ->whereNotNull('recurring_next_date')
            ->where('recurring_next_date', '<=', today())
            ->whereNotNull('yookassa_payment_method_id')
            ->get();
    }
}
