<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'project_id',
        'title',
        'context',
        'last_message_at',
        'pinned_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_message_at' => 'datetime',
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AiAction::class, 'conversation_id');
    }
}
