<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        return response()->json([
            'data' => [
                ...(new UserResource($request->user()))->resolve($request),
                'token' => $token instanceof ApiToken ? [
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'expires_at' => $token->expires_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}
