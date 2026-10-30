<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => 'INV-'.strtoupper(substr((string) $this->id, 0, 8)),
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'is_recurring' => $this->is_recurring,
            'recurring_interval' => $this->recurring_interval?->value,
            'recurring_next_date' => $this->recurring_next_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'client' => $this->whenLoaded('client', fn () => new ClientResource($this->client)),
            'task' => $this->whenLoaded('task', fn () => $this->task ? [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ] : null),
            'custom_fields' => app(CustomFieldService::class)->customFieldsWithDefaults($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
