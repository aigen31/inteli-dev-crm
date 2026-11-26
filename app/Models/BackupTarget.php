<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupTarget extends Model
{
    use HasUuids;

    protected $fillable = [
        'service_id', 'db_type', 'db_name', 'ftp_host', 'ftp_port', 'ftp_username',
        'ftp_password', 'remote_path', 'frequency_per_week', 'retention', 'quota_gb',
        'last_backup_at', 'last_backup_size', 'last_status', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'ftp_password' => 'encrypted',
            'frequency_per_week' => 'integer',
            'retention' => 'integer',
            'quota_gb' => 'decimal:2',
            'last_backup_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_backup_size' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(InfrastructureService::class, 'service_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(BackupRun::class, 'target_id');
    }
}
