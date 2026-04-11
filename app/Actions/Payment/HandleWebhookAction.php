<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\InvoiceStatus;
use App\Events\Invoice\InvoicePaid;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\YooKassaService;
use Illuminate\Support\Facades\Log;
use YooKassa\Model\Notification\NotificationFactory;

class HandleWebhookAction
{
    public function __construct(
        private readonly YooKassaService $yooKassaService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(array $data): void
    {
        $factory = new NotificationFactory();
        $notification = $factory->factory($data);
        $payment = $notification->getObject();

        $invoiceId = $payment->getMetadata()?->invoice_id ?? null;

        if (! $invoiceId) {
            Log::warning('Webhook received without invoice_id in metadata');
            return;
        }

        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            Log::warning("Webhook: Invoice not found: {$invoiceId}");
            return;
        }

        match ($notification->getEvent()) {
            'payment.succeeded' => $this->handlePaymentSucceeded($invoice, $payment),
            'payment.canceled' => $this->handlePaymentCanceled($invoice),
            'payment.waiting_for_capture' => $this->handleWaitingForCapture($invoice, $payment),
            'refund.succeeded' => $this->handleRefundSucceeded($invoice),
            default => Log::info("Unhandled webhook event: {$notification->getEvent()}"),
        };
    }

    private function handlePaymentSucceeded(Invoice $invoice, object $payment): void
    {
        if ($invoice->status !== InvoiceStatus::Pending) {
            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'yookassa_payment_id' => $payment->getId(),
        ]);

        if ($payment->getPaymentMethod()?->getSaved()) {
            $invoice->update([
                'yookassa_payment_method_id' => $payment->getPaymentMethod()->getId(),
            ]);
        }

        if ($invoice->is_recurring && $invoice->recurring_interval) {
            $invoice->update([
                'recurring_next_date' => $this->invoiceService->calculateNextRecurringDate(
                    $invoice->recurring_interval,
                ),
            ]);
        }

        InvoicePaid::dispatch($invoice);
    }

    private function handlePaymentCanceled(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Pending) {
            $invoice->update(['status' => InvoiceStatus::Cancelled]);
        }
    }

    private function handleWaitingForCapture(Invoice $invoice, object $payment): void
    {
        $this->yooKassaService->capturePayment(
            $payment->getId(),
            (string) $invoice->amount,
            $invoice->currency,
        );
    }

    private function handleRefundSucceeded(Invoice $invoice): void
    {
        $invoice->update(['status' => InvoiceStatus::Refunded]);
    }
}
