<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value_text',
        'value_number',
        'value_date',
        'value_boolean',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_date' => 'date',
            'value_boolean' => 'boolean',
            'value_json' => 'array',
        ];
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function typedValue(): mixed
    {
        $type = $this->customField?->type;

        return match ($type) {
            CustomFieldType::Number => $this->value_number !== null ? (float) $this->value_number : null,
            CustomFieldType::Currency => $this->value_number !== null ? (float) $this->value_number : null,
            CustomFieldType::Date => $this->value_date?->toDateString(),
            CustomFieldType::Checkbox => (bool) $this->value_boolean,
            CustomFieldType::Multiselect => $this->value_json ?? [],
            default => $this->value_text,
        };
    }

    public function displayValue(): mixed
    {
        $field = $this->customField;

        if ($field === null) {
            return $this->value_text;
        }

        if ($field->isSelect()) {
            $values = $this->typedValue();

            if (is_array($values)) {
                return array_map(fn ($value) => $field->optionLabel($value) ?? $value, $values);
            }

            return $field->optionLabel($values) ?? $values;
        }

        if ($field->type === CustomFieldType::Currency && $this->value_number !== null) {
            return number_format((float) $this->value_number, 2, ',', ' ');
        }

        if ($field->type === CustomFieldType::Checkbox) {
            return $this->value_boolean ? 'Да' : 'Нет';
        }

        if ($field->type === CustomFieldType::Date && $this->value_date !== null) {
            return $this->value_date->format('d.m.Y');
        }

        return $this->typedValue();
    }
}
