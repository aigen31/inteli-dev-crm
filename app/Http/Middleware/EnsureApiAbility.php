<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiAbility;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAbility
{
    public function handle(Request $request, Closure $next, string $ability = 'read'): Response
    {
        $token = $request->attributes->get('api_token');

        if (! $token instanceof ApiToken) {
            return response()->json(['message' => 'Требуется API-токен.', 'code' => 'api_token_missing'], 401);
        }

        $allowed = $ability === ApiAbility::Write->value
            ? $token->canWrite()
            : $token->canRead();

        if (! $allowed) {
            return response()->json([
                'message' => 'У токена нет права «'.$ability.'».',
                'code' => 'api_ability_denied',
            ], 403);
        }

        return $next($request);
    }
}
