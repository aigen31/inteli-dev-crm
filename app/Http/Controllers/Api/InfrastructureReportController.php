<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Infrastructure\BackupManifestSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Приём манифеста от агента на сервере хранения бэкапов.
 * Авторизация: Bearer-токен из INFRA_REPORT_TOKEN.
 */
class InfrastructureReportController extends Controller
{
    public function __invoke(Request $request, BackupManifestSync $sync): JsonResponse
    {
        $expected = (string) config('infrastructure.report_token');

        if ($expected === '') {
            return response()->json(['error' => 'report_disabled'], 503);
        }

        $given = (string) $request->bearerToken();

        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.service_id' => ['required', 'uuid'],
            'targets.*.files' => ['present', 'array'],
            'targets.*.files.*.name' => ['required', 'string', 'max:255'],
            'targets.*.files.*.size' => ['nullable', 'integer', 'min:0'],
            'targets.*.files.*.uploaded_at' => ['nullable', 'date'],
            'targets.*.files.*.sha256' => ['nullable', 'string', 'size:64'],
        ]);

        $result = $sync->syncPayload($data);

        return response()->json([
            'data' => [
                'targets' => $result['targets'],
                'runs' => $result['runs'],
                'received_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
