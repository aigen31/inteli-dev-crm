<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiActionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAction extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'tool_name',
        'arguments',
        'result',
        'status',
        'requires_confirmation',
        'confirmed_at',
        'executed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'status' => AiActionStatus::class,
            'requires_confirmation' => 'boolean',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
