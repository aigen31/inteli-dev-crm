<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'checklist' => $this->normalizedChecklist(),
            'default_type' => $this->default_type->value,
            'default_priority' => $this->default_priority->value,
            'default_status' => $this->default_status->value,
            'default_due_days' => $this->default_due_days,
            'estimated_minutes' => $this->estimated_minutes,
            'is_active' => $this->is_active,
            'is_shared' => $this->is_shared,
            'usage_count' => $this->usage_count,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null),
            'default_assignee' => $this->whenLoaded('defaultAssignee', fn () => $this->defaultAssignee ? [
                'id' => $this->defaultAssignee->id,
                'name' => $this->defaultAssignee->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
