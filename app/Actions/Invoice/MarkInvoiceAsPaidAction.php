<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Events\Invoice\InvoicePaid;
use App\Models\Invoice;

class MarkInvoiceAsPaidAction
{
    public function execute(Invoice $invoice): void
    {
        if ($invoice->payment_method !== PaymentMethod::Transfer) {
            throw new \InvalidArgumentException('Вручную можно подтверждать только прямой перевод.');
        }

        if (! $invoice->isPending()) {
            throw new \InvalidArgumentException('Подтвердить можно только счёт в статусе «Ожидает оплаты».');
        }

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        InvoicePaid::dispatch($invoice);
    }
}
