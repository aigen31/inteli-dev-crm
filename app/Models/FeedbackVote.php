<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackVote extends Model
{
    use HasUuids;

    protected $fillable = [
        'feedback_item_id',
        'user_id',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(FeedbackItem::class, 'feedback_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
