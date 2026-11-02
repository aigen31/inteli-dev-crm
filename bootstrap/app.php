<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AuthenticateKernel;
use App\Http\Middleware\EnsureApiAbility;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyYooKassaWebhook;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin()
            ? '/admin/dashboard'
            : '/dashboard');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'is_active' => EnsureUserIsActive::class,
            'is_admin' => EnsureUserIsAdmin::class,
            'yookassa_webhook' => VerifyYooKassaWebhook::class,
            'api.token' => AuthenticateApiToken::class,
            'kernel.token' => AuthenticateKernel::class,
            'api.ability' => EnsureApiAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
