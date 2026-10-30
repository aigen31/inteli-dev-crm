<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'checklist' => $this->normalizedChecklist(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'source' => $this->source->value,
            'estimated_minutes' => $this->estimated_minutes,
            'deadline' => $this->deadline?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'client' => $this->whenLoaded('client', fn () => new ClientResource($this->client)),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'template' => $this->whenLoaded('template', fn () => $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ] : null),
            'form_template' => $this->whenLoaded('formTemplate', fn () => $this->formTemplate ? [
                'id' => $this->formTemplate->id,
                'key' => $this->formTemplate->key,
                'name' => $this->formTemplate->name,
                'schema' => $this->formTemplate->normalizedSchema(),
            ] : null),
            'form_data' => $this->form_data,
            'custom_fields' => app(CustomFieldService::class)->customFieldsWithDefaults($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
