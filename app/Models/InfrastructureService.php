<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InfrastructureService extends Model
{
    use HasUuids;

    public const TYPE_BACKUP = 'backup';

    public const TYPE_CLUSTER = 'cluster';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'client_id', 'type', 'name', 'status', 'price_monthly', 'setup_fee',
        'currency', 'started_at', 'next_invoice_at', 'notes', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'started_at' => 'date',
            'next_invoice_at' => 'date',
            'meta' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function target(): HasOne
    {
        return $this->hasOne(BackupTarget::class, 'service_id');
    }

    public function cluster(): HasOne
    {
        return $this->hasOne(DrCluster::class, 'service_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'infrastructure_service_id');
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_CLUSTER ? 'DR-кластер' : 'Резервное копирование';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Активна',
            self::STATUS_SUSPENDED => 'Приостановлена',
            default => 'Черновик',
        };
    }
}
