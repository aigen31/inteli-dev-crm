<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentLog extends Model
{
    use HasUuids;

    public const VERSION = '2026-11-06';

    protected $fillable = [
        'user_id',
        'form',
        'document',
        'version',
        'accepted_at',
        'ip',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
