<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_calls',
        'tool_call_id',
        'attachments',
        'trace',
        'tokens_in',
        'tokens_out',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'tool_calls' => 'array',
            'attachments' => 'array',
            'trace' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
