<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Invoice;
use App\Services\YooKassaService;

class CreatePaymentAction
{
    public function __construct(
        private readonly YooKassaService $yooKassaService,
    ) {}

    public function execute(Invoice $invoice, string $returnUrl): string
    {
        $payment = $this->yooKassaService->createPayment($invoice, $returnUrl);

        $invoice->update([
            'yookassa_payment_id' => $payment->getId(),
        ]);

        return $payment->getConfirmation()->getConfirmationUrl();
    }
}
