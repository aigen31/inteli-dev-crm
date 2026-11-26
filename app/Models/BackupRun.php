<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'target_id', 'filename', 'size_bytes', 'checksum', 'status',
        'uploaded_at', 'checked_at', 'message',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(BackupTarget::class, 'target_id');
    }
}
