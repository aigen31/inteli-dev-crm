<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\RecurringInterval;
use App\Enums\TaskStatus;
use App\Events\Invoice\InvoiceCreated;
use App\Models\Invoice;
use App\Models\Task;
use App\Services\InvoiceService;

class CreateInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(array $data): Invoice
    {
        $task = Task::findOrFail($data['task_id']);

        if ($task->status === TaskStatus::RejectedByAdmin) {
            throw new \InvalidArgumentException('Нельзя выставить счёт для отклонённой задачи.');
        }

        $paymentMethod = PaymentMethod::from($data['payment_method'] ?? PaymentMethod::YooKassa->value);

        // Прямой перевод принимается вне кассы, поэтому автосписаний для него нет.
        $isRecurring = $paymentMethod->isOnline() && (bool) ($data['is_recurring'] ?? false);

        $invoice = Invoice::create([
            'task_id' => $task->id,
            'client_id' => $task->client_id,
            'amount' => $data['amount'],
            'currency' => 'RUB',
            'description' => $data['description'],
            'status' => InvoiceStatus::Pending,
            'payment_method' => $paymentMethod,
            'is_recurring' => $isRecurring,
            'recurring_interval' => $isRecurring ? ($data['recurring_interval'] ?? null) : null,
            'recurring_next_date' => $isRecurring && isset($data['recurring_interval'])
                ? $this->invoiceService->calculateNextRecurringDate(
                    RecurringInterval::from($data['recurring_interval'])
                )
                : null,
        ]);

        InvoiceCreated::dispatch($invoice);

        return $invoice;
    }
}
