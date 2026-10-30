<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCustomFields
{
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'entity');
    }

    /**
     * @return array<string, mixed>
     */
    public function customFieldsData(): array
    {
        $this->loadMissing('customFieldValues.customField');

        $data = [];

        foreach ($this->customFieldValues as $value) {
            if ($value->customField === null) {
                continue;
            }

            $data[$value->customField->key] = [
                'field_id' => $value->customField->id,
                'label' => $value->customField->label,
                'type' => $value->customField->type->value,
                'value' => $value->typedValue(),
                'display' => $value->displayValue(),
            ];
        }

        return $data;
    }
}
