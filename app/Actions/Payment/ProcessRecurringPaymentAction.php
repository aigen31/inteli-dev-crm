<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Events\Invoice\InvoicePaid;
use App\Events\Invoice\RecurringPaymentFailed;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\YooKassaService;
use Illuminate\Support\Facades\Log;

class ProcessRecurringPaymentAction
{
    public function __construct(
        private readonly YooKassaService $yooKassaService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $originalInvoice): void
    {
        if (! $originalInvoice->yookassa_payment_method_id) {
            Log::warning("Recurring payment skipped: no payment method for invoice {$originalInvoice->id}");

            return;
        }

        $newInvoice = Invoice::create([
            'task_id' => $originalInvoice->task_id,
            'client_id' => $originalInvoice->client_id,
            'amount' => $originalInvoice->amount,
            'currency' => $originalInvoice->currency,
            'description' => $originalInvoice->description,
            'status' => InvoiceStatus::Pending,
            'payment_method' => PaymentMethod::YooKassa,
            'is_recurring' => true,
            'recurring_interval' => $originalInvoice->recurring_interval,
            'yookassa_payment_method_id' => $originalInvoice->yookassa_payment_method_id,
        ]);

        try {
            $payment = $this->yooKassaService->createRecurringPayment(
                $newInvoice,
                $originalInvoice->yookassa_payment_method_id,
            );

            $newInvoice->update([
                'yookassa_payment_id' => $payment->getId(),
            ]);

            if ($payment->getStatus() === 'succeeded') {
                $newInvoice->update([
                    'status' => InvoiceStatus::Paid,
                    'paid_at' => now(),
                ]);

                $originalInvoice->update([
                    'recurring_next_date' => $this->invoiceService->calculateNextRecurringDate(
                        $originalInvoice->recurring_interval,
                    ),
                ]);

                InvoicePaid::dispatch($newInvoice);
            }
        } catch (\Exception $e) {
            Log::error("Recurring payment failed for invoice {$newInvoice->id}: {$e->getMessage()}");
            RecurringPaymentFailed::dispatch($newInvoice, 1);
        }
    }
}
