<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'custom_fields' => app(CustomFieldService::class)->customFieldsWithDefaults($this->resource),
            'tasks_count' => $this->whenCounted('clientTasks'),
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
