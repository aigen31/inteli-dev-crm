<?php

declare(strict_types=1);

namespace App\Services\Infrastructure;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\InfrastructureService;
use App\Models\Invoice;

class InfrastructureBilling
{
    /**
     * Создаёт счёт за период, если его ещё нет. Идемпотентно.
     */
    public function createInvoice(InfrastructureService $service, ?string $period = null): ?Invoice
    {
        if ((float) $service->price_monthly <= 0) {
            return null;
        }

        $period = $period ?? now()->format('Y-m');

        if ($service->invoices()->where('service_period', $period)->exists()) {
            return null;
        }

        $invoice = Invoice::query()->create([
            'client_id' => $service->client_id,
            'infrastructure_service_id' => $service->id,
            'service_period' => $period,
            'amount' => $service->price_monthly,
            'currency' => $service->currency ?: 'RUB',
            'description' => $service->typeLabel().' «'.$service->name.'» за '.$period,
            'status' => InvoiceStatus::Pending,
            'payment_method' => PaymentMethod::Transfer,
        ]);

        $service->update(['next_invoice_at' => now()->addMonth()->startOfMonth()->toDateString()]);

        return $invoice;
    }

    /**
     * @return array{created:int}
     */
    public function runMonthly(): array
    {
        $created = 0;

        InfrastructureService::query()
            ->where('status', InfrastructureService::STATUS_ACTIVE)
            ->whereNotNull('started_at')
            ->whereDate('started_at', '<=', today())
            ->each(function (InfrastructureService $service) use (&$created): void {
                if ($this->createInvoice($service) !== null) {
                    $created++;
                }
            });

        return ['created' => $created];
    }
}
