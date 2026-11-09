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
        $taskIds = array_values(array_unique($data['task_ids'] ?? []));
        $tasks = Task::query()->whereIn('id', $taskIds)->get();

        if ($tasks->isEmpty()) {
            throw new \InvalidArgumentException('Выберите хотя бы одну задачу для счёта.');
        }

        if ($tasks->contains(fn (Task $task): bool => $task->status === TaskStatus::RejectedByAdmin)) {
            throw new \InvalidArgumentException('Нельзя выставить счёт для отклонённой задачи.');
        }

        if ($tasks->pluck('client_id')->unique()->count() > 1) {
            throw new \InvalidArgumentException('Все задачи в счёте должны принадлежать одному клиенту.');
        }

        $amount = isset($data['amount']) && $data['amount'] !== ''
            ? (float) $data['amount']
            : (float) $tasks->sum(fn (Task $task): float => (float) ($task->budget ?? 0));

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Укажите бюджет хотя бы одной задачи или сумму счёта.');
        }

        $firstTask = $tasks->first();
        $paymentMethod = PaymentMethod::from($data['payment_method'] ?? PaymentMethod::YooKassa->value);

        // Прямой перевод принимается вне кассы, поэтому автосписаний для него нет.
        $isRecurring = $paymentMethod->isOnline() && (bool) ($data['is_recurring'] ?? false);

        $description = trim((string) ($data['description'] ?? ''));

        if ($description === '') {
            $description = 'Счёт по задачам: '.$tasks->pluck('title')->take(3)->implode(', ');
            if ($tasks->count() > 3) {
                $description .= ' и ещё '.($tasks->count() - 3);
            }
        }

        $invoice = Invoice::create([
            'task_id' => $firstTask->id,
            'client_id' => $firstTask->client_id,
            'amount' => $amount,
            'currency' => 'RUB',
            'description' => mb_substr($description, 0, 500),
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

        $invoice->tasks()->sync(
            $tasks->mapWithKeys(fn (Task $task): array => [
                $task->id => ['amount' => (float) ($task->budget ?? 0)],
            ])->all()
        );

        InvoiceCreated::dispatch($invoice);

        return $invoice;
    }
}
