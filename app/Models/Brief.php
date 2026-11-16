<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BriefStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brief extends Model
{
    use HasUuids;

    protected $fillable = [
        'brief_template_id',
        'template_version',
        'client_id',
        'project_id',
        'created_by',
        'title',
        'status',
        'data',
        'schema_snapshot',
        'admin_note',
        'progress',
        'submitted_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'schema_snapshot' => 'array',
            'status' => BriefStatus::class,
            'template_version' => 'integer',
            'progress' => 'integer',
            'submitted_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BriefTemplate::class, 'brief_template_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BriefRevision::class)->orderByDesc('version');
    }

    /**
     * Схема, по которой заполнялся бриф: снимок отправленного важнее шаблона.
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $snapshot = $this->schema_snapshot;

        if (is_array($snapshot) && ($snapshot['sections'] ?? null) !== null) {
            return $snapshot;
        }

        $schema = $this->template?->schema;

        return is_array($schema) ? $schema : [];
    }

    /**
     * Секции схемы.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        $sections = $this->schema()['sections'] ?? [];

        return is_array($sections) ? array_values(array_filter($sections, 'is_array')) : [];
    }

    /**
     * Ответы: {field_key: value}.
     *
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        $data = $this->data;

        return is_array($data) ? $data : [];
    }

    public function isEditable(): bool
    {
        return $this->status instanceof BriefStatus && $this->status->isEditable();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('client_id', $user->id)
                ->orWhereHas('project.members', fn (Builder $members) => $members->where('user_id', $user->id));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin() || $this->client_id === $user->id) {
            return true;
        }

        return $this->project?->members()->where('user_id', $user->id)->exists() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'progress' => $this->progress,
            'template' => $this->template ? [
                'id' => $this->template->id,
                'title' => $this->template->title,
                'icon' => $this->template->icon,
                'slug' => $this->template->slug,
            ] : null,
            'client' => $this->client ? ['id' => $this->client->id, 'name' => $this->client->name] : null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'key' => $this->project->key,
                'name' => $this->project->name,
            ] : null,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
