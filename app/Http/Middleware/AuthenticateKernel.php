<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service-token guard for the kernel -> CRM tool contract.
 *
 * This route is server-to-server only. It must never be reachable from the
 * Inertia UI with a user session.
 */
class AuthenticateKernel
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('ai.kernel_provider_token');
        if ($expected === '') {
            abort(404);
        }

        $provided = (string) $request->bearerToken();
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'unauthorized');
        }

        if ($request->header('X-Kernel-Channel') !== 'crm') {
            abort(401, 'wrong kernel channel');
        }

        return $next($request);
    }
}
