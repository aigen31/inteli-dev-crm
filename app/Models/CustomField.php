<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    use HasUuids;

    protected $fillable = [
        'entity_type',
        'key',
        'label',
        'type',
        'options',
        'default_value',
        'validation',
        'is_required',
        'is_active',
        'show_in_list',
        'client_editable',
        'sort_order',
        'help_text',
        'placeholder',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => CustomFieldEntity::class,
            'type' => CustomFieldType::class,
            'options' => 'array',
            'default_value' => 'json',
            'validation' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'show_in_list' => 'boolean',
            'client_editable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForEntity(Builder $query, CustomFieldEntity|string $entity): Builder
    {
        $value = $entity instanceof CustomFieldEntity ? $entity->value : $entity;

        return $query->where('entity_type', $value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return list<array{value: string, label: string, color?: string}>
     */
    public function normalizedOptions(): array
    {
        $options = $this->options ?? [];

        return array_values(array_map(function (mixed $option): array {
            if (is_string($option)) {
                return ['value' => $option, 'label' => $option];
            }

            return [
                'value' => (string) ($option['value'] ?? ''),
                'label' => (string) ($option['label'] ?? $option['value'] ?? ''),
                ...(isset($option['color']) ? ['color' => (string) $option['color']] : []),
            ];
        }, $options));
    }

    public function optionLabel(mixed $value): ?string
    {
        foreach ($this->normalizedOptions() as $option) {
            if ($option['value'] === (string) $value) {
                return $option['label'];
            }
        }

        return null;
    }

    public function isSelect(): bool
    {
        return in_array($this->type, [CustomFieldType::Select, CustomFieldType::Multiselect], true);
    }
}
