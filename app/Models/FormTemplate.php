<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'summary',
        'description',
        'icon',
        'tone',
        'category',
        'schema',
        'is_active',
        'sort_order',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return array{fields: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function normalizedSchema(): array
    {
        $schema = is_array($this->schema) ? $this->schema : [];

        return [
            'fields' => array_values(array_filter($schema['fields'] ?? [], 'is_array')),
            'groups' => array_values(array_filter($schema['groups'] ?? [], 'is_array')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        return $this->normalizedSchema()['fields'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groups(): array
    {
        return $this->normalizedSchema()['groups'];
    }

    public function fieldCount(): int
    {
        $count = count($this->fields());

        foreach ($this->groups() as $group) {
            $count += count($group['fields'] ?? []);
        }

        return $count;
    }

    public function requiredCount(): int
    {
        $count = count(array_filter($this->fields(), fn (array $field) => (bool) ($field['required'] ?? false)));

        foreach ($this->groups() as $group) {
            $count += count(array_filter($group['fields'] ?? [], fn (array $field) => is_array($field) && (bool) ($field['required'] ?? false)));
        }

        return $count;
    }

    public function estimatedMinutes(): int
    {
        return max(2, (int) ceil($this->fieldCount() * 0.6));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return array<string, mixed>
     */
    public function toLibraryArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'icon' => $this->icon,
            'tone' => $this->tone,
            'category' => $this->category,
            'schema' => $this->normalizedSchema(),
            'field_count' => $this->fieldCount(),
            'required_count' => $this->requiredCount(),
            'estimated_minutes' => $this->estimatedMinutes(),
            'usage_count' => $this->usage_count,
        ];
    }
}
