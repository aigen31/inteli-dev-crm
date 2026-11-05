<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectMemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectMemberRole::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
