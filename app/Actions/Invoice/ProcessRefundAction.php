<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\YooKassaService;

class ProcessRefundAction
{
    public function __construct(
        private readonly YooKassaService $yooKassaService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->isPaid() || ! $invoice->yookassa_payment_id) {
            throw new \InvalidArgumentException('Возврат возможен только для оплаченного счёта.');
        }

        $this->yooKassaService->createRefund(
            $invoice->yookassa_payment_id,
            (string) $invoice->amount,
            $invoice->currency,
        );

        $invoice->update(['status' => InvoiceStatus::Refunded]);

        return $invoice;
    }
}
