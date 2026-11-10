<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsExecutor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isExecutor() && ! $request->user()?->hasExecutorDelegation()) {
            abort(403, 'Доступ только для исполнителя проекта.');
        }

        return $next($request);
    }
}
