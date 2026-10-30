<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\RecurringInterval;
use App\Models\Concerns\HasCustomFields;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasCustomFields, HasFactory, HasUuids;

    protected $fillable = [
        'task_id',
        'client_id',
        'amount',
        'currency',
        'description',
        'status',
        'payment_method',
        'is_recurring',
        'recurring_interval',
        'recurring_next_date',
        'yookassa_payment_id',
        'yookassa_payment_method_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'is_recurring' => 'boolean',
            'recurring_interval' => RecurringInterval::class,
            'recurring_next_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function isPending(): bool
    {
        return $this->status === InvoiceStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }
}
