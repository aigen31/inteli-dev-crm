<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomFieldService
{
    /**
     * @return Collection<int, CustomField>
     */
    public function definitions(CustomFieldEntity|string $entity, bool $activeOnly = true): Collection
    {
        return CustomField::query()
            ->forEntity($entity)
            ->when($activeOnly, fn (Builder $query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function definitionByKey(CustomFieldEntity|string $entity, string $key): ?CustomField
    {
        return CustomField::query()->forEntity($entity)->where('key', $key)->first();
    }

    public function entityFor(Model $model): CustomFieldEntity
    {
        return match (true) {
            $model instanceof User => CustomFieldEntity::Client,
            $model instanceof Task => CustomFieldEntity::Task,
            $model instanceof Invoice => CustomFieldEntity::Invoice,
            default => throw new \InvalidArgumentException('Unsupported custom field entity: '.$model::class),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesFor(Model $entity): array
    {
        return $entity->customFieldsData();
    }

    /**
     * Persist a partial set of custom field values. Unknown keys are ignored.
     *
     * @param  array<string, mixed>  $values
     */
    public function sync(Model $entity, array $values, ?User $actor = null): void
    {
        $entityType = $this->entityFor($entity);
        $definitions = $this->definitions($entityType)->keyBy('key');
        $entity->loadMissing('customFieldValues');

        foreach ($values as $key => $value) {
            $field = $definitions->get($key);

            if (! $field instanceof CustomField) {
                continue;
            }

            $this->validateValue($field, $value);

            if ($this->isEmpty($field, $value)) {
                if ($field->is_required) {
                    throw ValidationException::withMessages([
                        "custom_fields.$key" => "Поле «{$field->label}» обязательно.",
                    ]);
                }

                $entity->customFieldValues()
                    ->where('custom_field_id', $field->id)
                    ->delete();

                continue;
            }

            $columns = [
                'value_text' => null,
                'value_number' => null,
                'value_date' => null,
                'value_boolean' => null,
                'value_json' => null,
            ];

            $columns[$field->type->valueColumn()] = $this->storageValue($field, $value);

            $entity->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                $columns,
            );
        }

        $entity->unsetRelation('customFieldValues');

        foreach ($definitions as $field) {
            if (! $field->is_required) {
                continue;
            }

            $exists = $entity->customFieldValues()
                ->where('custom_field_id', $field->id)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    "custom_fields.$field->key" => "Поле «{$field->label}» обязательно.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setValue(Model $entity, string $key, mixed $value): CustomFieldValueResult
    {
        $field = $this->definitionByKey($this->entityFor($entity), $key);

        if (! $field instanceof CustomField) {
            throw ValidationException::withMessages(['key' => "Поле «{$key}» не найдено."]);
        }

        $this->sync($entity, [$key => $value]);

        $entity->load('customFieldValues.customField');
        $stored = $entity->customFieldValues->firstWhere('custom_field_id', $field->id);

        return new CustomFieldValueResult($field, $stored?->typedValue(), $stored?->displayValue());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, CustomFieldEntity|string $entity, array $filters): Builder
    {
        $definitions = $this->definitions($entity)->keyBy('key');

        foreach ($filters as $key => $value) {
            $field = $definitions->get($key);

            if (! $field instanceof CustomField || $value === null || $value === '') {
                continue;
            }

            $query->whereHas('customFieldValues', function (Builder $subquery) use ($field, $value): void {
                $subquery->where('custom_field_id', $field->id);

                if ($field->type === CustomFieldType::Multiselect) {
                    $subquery->whereJsonContains('value_json', $value);

                    return;
                }

                $subquery->where($field->type->valueColumn(), $value);
            });
        }

        return $query;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializedDefinitions(CustomFieldEntity|string $entity, bool $activeOnly = true): array
    {
        return $this->definitions($entity, $activeOnly)
            ->map(fn (CustomField $field) => $this->serializeDefinition($field))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDefinition(CustomField $field): array
    {
        return [
            'id' => $field->id,
            'entity_type' => $field->entity_type->value,
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'type_label' => $field->type->label(),
            'options' => $field->normalizedOptions(),
            'default_value' => $field->default_value,
            'is_required' => $field->is_required,
            'is_active' => $field->is_active,
            'show_in_list' => $field->show_in_list,
            'client_editable' => $field->client_editable,
            'sort_order' => $field->sort_order,
            'help_text' => $field->help_text,
            'placeholder' => $field->placeholder,
            'validation' => $field->validation,
        ];
    }

    /**
     * Keyed custom field data with defaults for fields that have no stored value.
     *
     * @return array<string, array<string, mixed>>
     */
    public function customFieldsWithDefaults(Model $entity): array
    {
        $data = $entity->customFieldsData();

        foreach ($this->definitions($this->entityFor($entity)) as $field) {
            if (isset($data[$field->key])) {
                continue;
            }

            $raw = $field->default_value;

            $data[$field->key] = [
                'field_id' => $field->id,
                'label' => $field->label,
                'type' => $field->type->value,
                'value' => $raw,
                'display' => $this->defaultDisplay($field, $raw),
            ];
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializedValues(Model $entity): array
    {
        $entity->loadMissing('customFieldValues.customField');
        $definitions = $this->definitions($this->entityFor($entity));
        $stored = $entity->customFieldValues->keyBy('custom_field_id');

        return $definitions->map(function (CustomField $field) use ($stored): array {
            $value = $stored->get($field->id);
            $raw = $value?->typedValue() ?? $field->default_value;

            return [
                ...$this->serializeDefinition($field),
                'value' => $raw,
                'display' => $value?->displayValue() ?? $this->defaultDisplay($field, $raw),
            ];
        })->values()->all();
    }

    private function defaultDisplay(CustomField $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($field->isSelect()) {
            if (is_array($value)) {
                return array_map(fn ($item) => $field->optionLabel($item) ?? $item, $value);
            }

            return $field->optionLabel($value) ?? $value;
        }

        if ($field->type === CustomFieldType::Checkbox) {
            return $value ? 'Да' : 'Нет';
        }

        if ($field->type === CustomFieldType::Currency) {
            return number_format((float) $value, 2, ',', ' ');
        }

        return $value;
    }

    public function assertKeyAvailable(CustomFieldEntity $entity, string $key, ?string $ignoreId = null): void
    {
        $exists = CustomField::query()
            ->forEntity($entity)
            ->where('key', $key)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'key' => 'Поле с таким ключом уже существует для этой сущности.',
            ]);
        }
    }

    private function validateValue(CustomField $field, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $rules = $this->rulesFor($field);

        if (! $field->is_required) {
            array_unshift($rules, 'nullable');
        }

        $rules = ['value' => $rules];

        if ($field->type === CustomFieldType::Multiselect) {
            $rules['value.*'] = ['string', Rule::in(array_column($field->normalizedOptions(), 'value'))];
        }

        $validator = Validator::make(['value' => $value], $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                "custom_fields.$field->key" => $validator->errors()->first('value'),
            ]);
        }
    }

    /**
     * @return list<mixed>
     */
    private function rulesFor(CustomField $field): array
    {
        $optionValues = array_column($field->normalizedOptions(), 'value');

        return match ($field->type) {
            CustomFieldType::Text => ['string', 'max:1000'],
            CustomFieldType::Textarea => ['string', 'max:20000'],
            CustomFieldType::Number => ['numeric'],
            CustomFieldType::Currency => ['numeric', 'min:0'],
            CustomFieldType::Date => ['date'],
            CustomFieldType::Select => ['string', Rule::in($optionValues)],
            CustomFieldType::Multiselect => ['array', 'max:50'],
            CustomFieldType::Checkbox => ['boolean'],
            CustomFieldType::Url => ['url:http,https', 'max:2048'],
            CustomFieldType::Email => ['email:rfc', 'max:255'],
            CustomFieldType::Phone => ['string', 'max:40'],
        };
    }

    private function isEmpty(CustomField $field, mixed $value): bool
    {
        if ($field->type === CustomFieldType::Checkbox) {
            return $value === null;
        }

        if ($field->type === CustomFieldType::Multiselect) {
            return ! is_array($value) || $value === [];
        }

        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function storageValue(CustomField $field, mixed $value): mixed
    {
        return match ($field->type) {
            CustomFieldType::Number, CustomFieldType::Currency => (float) $value,
            CustomFieldType::Date => is_string($value) ? $value : $value?->format('Y-m-d'),
            CustomFieldType::Checkbox => (bool) $value,
            CustomFieldType::Multiselect => array_values((array) $value),
            default => (string) $value,
        };
    }
}
