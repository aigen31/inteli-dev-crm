<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
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

        $invoice = Invoice::create([
            'task_id' => $task->id,
            'client_id' => $task->client_id,
            'amount' => $data['amount'],
            'currency' => 'RUB',
            'description' => $data['description'],
            'status' => InvoiceStatus::Pending,
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurring_interval' => $data['recurring_interval'] ?? null,
            'recurring_next_date' => isset($data['recurring_interval'])
                ? $this->invoiceService->calculateNextRecurringDate(
                    \App\Enums\RecurringInterval::from($data['recurring_interval'])
                )
                : null,
        ]);

        InvoiceCreated::dispatch($invoice);

        return $invoice;
    }
}
