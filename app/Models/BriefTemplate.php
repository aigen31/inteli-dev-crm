<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BriefTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'title',
        'summary',
        'icon',
        'estimated_minutes',
        'schema',
        'schema_hash',
        'version',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'estimated_minutes' => 'integer',
            'version' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function briefs(): HasMany
    {
        return $this->hasMany(Brief::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Секции схемы с полями.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        $sections = $this->schema['sections'] ?? [];

        return is_array($sections) ? array_values(array_filter($sections, 'is_array')) : [];
    }

    /**
     * Количество полей во всех секциях.
     */
    public function fieldCount(): int
    {
        $count = 0;

        foreach ($this->sections() as $section) {
            $fields = $section['fields'] ?? [];
            $count += is_array($fields) ? count($fields) : 0;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function toLibraryArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'icon' => $this->icon,
            'estimated_minutes' => $this->estimated_minutes,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'field_count' => $this->fieldCount(),
            'section_count' => count($this->sections()),
        ];
    }
}
