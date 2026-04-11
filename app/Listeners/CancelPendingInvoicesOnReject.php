<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Events\Task\TaskStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

class CancelPendingInvoicesOnReject implements ShouldQueue
{
    public function handle(TaskStatusChanged $event): void
    {
        if ($event->newStatus !== TaskStatus::RejectedByAdmin) {
            return;
        }

        $event->task->invoices()
            ->where('status', InvoiceStatus::Pending)
            ->update(['status' => InvoiceStatus::Cancelled]);
    }
}
