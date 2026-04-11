<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Events\Invoice\InvoiceCancelled;
use App\Models\Invoice;

class CancelInvoiceAction
{
    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->isPending()) {
            throw new \InvalidArgumentException('Можно отменить только неоплаченный счёт.');
        }

        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        InvoiceCancelled::dispatch($invoice);

        return $invoice;
    }
}
