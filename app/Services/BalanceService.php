<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\BalanceTransaction;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;

/**
 * Баланс пользователя: ручные корректировки админа и автосписание
 * стоимости завершённой задачи.
 */
class BalanceService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function adjust(User $user, float $amount, string $reason, ?string $description = null, array $meta = [], ?User $by = null): BalanceTransaction
    {
        $user->refresh();
        $balance = round((float) $user->balance + $amount, 2);

        $user->update(['balance' => $balance]);

        return BalanceTransaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'balance_after' => $balance,
            'reason' => $reason,
            'description' => $description,
            'task_id' => $meta['task_id'] ?? null,
            'invoice_id' => $meta['invoice_id'] ?? null,
            'created_by' => $by?->id,
        ]);
    }

    /**
     * Завершённая задача со стоимостью создаёт счёт «прямой перевод» и
     * уменьшает баланс заказчика. Повторный вызов счёт не дублирует.
     */
    public function settleCompletedTask(Task $task, ?User $by = null): ?Invoice
    {
        $budget = round((float) ($task->budget ?? 0), 2);

        if ($budget <= 0 || $task->client_id === null) {
            return null;
        }

        if (Invoice::query()->where('task_id', $task->id)->exists()) {
            return null;
        }

        $invoice = Invoice::query()->create([
            'task_id' => $task->id,
            'client_id' => $task->client_id,
            'amount' => $budget,
            'currency' => 'RUB',
            'description' => 'Задача: '.$task->title,
            'status' => InvoiceStatus::Pending,
            'payment_method' => PaymentMethod::Transfer,
        ]);

        $this->adjust(
            User::query()->findOrFail($task->client_id),
            -$budget,
            'task_completed',
            'Списание за задачу «'.$task->title.'»',
            ['task_id' => $task->id, 'invoice_id' => $invoice->id],
            $by,
        );

        return $invoice;
    }
}
