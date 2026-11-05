<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'project_id',
        'file_attachment_id',
        'name',
        'mime_type',
        'text',
        'char_count',
    ];

    protected function casts(): array
    {
        return [
            'char_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(FileAttachment::class, 'file_attachment_id');
    }

    public function preview(int $limit = 300): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $this->text));

        return mb_substr($text, 0, $limit);
    }
}
