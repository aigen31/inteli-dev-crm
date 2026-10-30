<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ai\CrmToolbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    public function overview(Request $request, CrmToolbox $toolbox): JsonResponse
    {
        return response()->json([
            'data' => $toolbox->execute('overview', $request->user(), []),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'app' => config('app.name'),
                'time' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ]);
    }
}
