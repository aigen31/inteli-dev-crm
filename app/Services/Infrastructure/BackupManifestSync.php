<?php

declare(strict_types=1);

namespace App\Services\Infrastructure;

use App\Models\BackupRun;
use App\Models\BackupTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Импорт манифеста, который формирует агент на сервере хранения.
 *
 * Формат:
 * {
 *   "targets": [{
 *     "service_id": "uuid",
 *     "files": [{"name": "...", "size": 123, "uploaded_at": "...", "sha256": "..."}]
 *   }]
 * }
 */
class BackupManifestSync
{
    /**
     * @return array{targets:int, runs:int}
     */
    public function sync(?string $path = null): array
    {
        $path = $path ?? (string) config('infrastructure.manifest_path');

        if (! File::exists($path)) {
            return ['targets' => 0, 'runs' => 0];
        }

        $payload = json_decode((string) File::get($path), true);

        return $this->syncPayload(is_array($payload) ? $payload : []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{targets:int, runs:int}
     */
    public function syncPayload(array $payload): array
    {
        $entries = is_array($payload['targets'] ?? null) ? $payload['targets'] : [];

        $targets = 0;
        $runs = 0;

        foreach ($entries as $entry) {
            $serviceId = (string) ($entry['service_id'] ?? '');
            $target = BackupTarget::query()->where('service_id', $serviceId)->first();

            if ($target === null) {
                continue;
            }

            $targets++;
            $latest = null;

            foreach ((array) ($entry['files'] ?? []) as $file) {
                $name = (string) ($file['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $uploadedAt = isset($file['uploaded_at']) ? Carbon::parse($file['uploaded_at']) : now();

                BackupRun::query()->updateOrCreate(
                    ['target_id' => $target->id, 'filename' => $name],
                    [
                        'size_bytes' => (int) ($file['size'] ?? 0),
                        'checksum' => $file['sha256'] ?? null,
                        'status' => 'verified',
                        'uploaded_at' => $uploadedAt,
                        'checked_at' => now(),
                    ],
                );

                $runs++;

                if ($latest === null || $uploadedAt->greaterThan($latest)) {
                    $latest = $uploadedAt;
                    $target->update([
                        'last_backup_at' => $uploadedAt,
                        'last_backup_size' => (int) ($file['size'] ?? 0),
                        'last_status' => 'verified',
                        'last_checked_at' => now(),
                    ]);
                }
            }
        }

        return ['targets' => $targets, 'runs' => $runs];
    }
}
