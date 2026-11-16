<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'brief_id',
        'version',
        'data',
        'schema',
        'created_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'schema' => 'array',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            $revision->created_at = $revision->created_at ?? now();
        });
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
