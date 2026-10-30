<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type->value,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'options' => $this->normalizedOptions(),
            'default_value' => $this->default_value,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'show_in_list' => $this->show_in_list,
            'client_editable' => $this->client_editable,
            'sort_order' => $this->sort_order,
            'help_text' => $this->help_text,
            'placeholder' => $this->placeholder,
        ];
    }
}
