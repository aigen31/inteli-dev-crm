<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FileAttachmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAttachment extends Model
{
    /** @use HasFactory<FileAttachmentFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'uploaded_by',
        'original_name',
        'stored_path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attachment) {
            $attachment->created_at = $attachment->created_at ?? now();
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
