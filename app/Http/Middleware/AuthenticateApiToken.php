<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(
        private readonly ApiTokenService $tokens,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if ($plain === null || $plain === '') {
            return response()->json([
                'message' => 'Передайте API-токен в заголовке Authorization: Bearer <token>.',
                'code' => 'api_token_missing',
            ], 401);
        }

        $token = $this->tokens->findByPlainText($plain);

        if ($token === null) {
            return response()->json([
                'message' => 'Токен недействителен или истёк.',
                'code' => 'api_token_invalid',
            ], 401);
        }

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        $this->tokens->touch($token);

        return $next($request);
    }
}
