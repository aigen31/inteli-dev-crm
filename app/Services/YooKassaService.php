<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use YooKassa\Client;

class YooKassaService
{
    private Client $client;

    private bool $configured = false;

    public function __construct()
    {
        $this->client = new Client();

        $shopId = (string) config('services.yookassa.shop_id');
        $secretKey = (string) config('services.yookassa.secret_key');

        if ($shopId !== '' && $secretKey !== '') {
            // SDK принимает shopId как int, строка из env ломает типизированное свойство.
            $this->client->setAuth((int) $shopId, $secretKey);
            $this->configured = true;
        }
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function createPayment(Invoice $invoice, string $returnUrl): object
    {
        $this->ensureConfigured();

        return $this->client->createPayment([
            'amount' => [
                'value' => $invoice->amount,
                'currency' => $invoice->currency,
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl,
            ],
            'capture' => true,
            'description' => $invoice->description,
            'save_payment_method' => $invoice->is_recurring,
            'metadata' => [
                'invoice_id' => $invoice->id,
            ],
        ], uniqid('', true));
    }

    public function createRecurringPayment(Invoice $invoice, string $paymentMethodId): object
    {
        $this->ensureConfigured();

        return $this->client->createPayment([
            'amount' => [
                'value' => $invoice->amount,
                'currency' => $invoice->currency,
            ],
            'capture' => true,
            'payment_method_id' => $paymentMethodId,
            'description' => $invoice->description,
            'metadata' => [
                'invoice_id' => $invoice->id,
            ],
        ], uniqid('', true));
    }

    public function createRefund(string $paymentId, string $amount, string $currency = 'RUB'): object
    {
        $this->ensureConfigured();

        return $this->client->createRefund([
            'payment_id' => $paymentId,
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
        ], uniqid('', true));
    }

    public function getPaymentInfo(string $paymentId): object
    {
        return $this->client->getPaymentInfo($paymentId);
    }

    public function capturePayment(string $paymentId, string $amount, string $currency = 'RUB'): object
    {
        return $this->client->capturePayment([
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
        ], $paymentId);
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured) {
            throw new \RuntimeException('ЮКасса не настроена: заполните YOOKASSA_SHOP_ID и YOOKASSA_SECRET_KEY.');
        }
    }
}
