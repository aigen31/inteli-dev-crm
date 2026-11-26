<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrCluster extends Model
{
    use HasUuids;

    protected $fillable = [
        'service_id', 'tier', 'size', 'region', 'rto_minutes', 'rpo_minutes',
        'status', 'last_drill_at', 'runbook',
    ];

    protected function casts(): array
    {
        return [
            'last_drill_at' => 'datetime',
            'rto_minutes' => 'integer',
            'rpo_minutes' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(InfrastructureService::class, 'service_id');
    }
}
