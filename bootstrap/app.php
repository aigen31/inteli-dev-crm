<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AuthenticateKernel;
use App\Http\Middleware\BriefLinkHeaders;
use App\Http\Middleware\EnsureApiAbility;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsExecutor;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PublicBriefLocale;
use App\Http\Middleware\RecordNavigationVisit;
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

        $middleware->redirectUsersTo(fn (Request $request) => match (true) {
            $request->user()?->isAdmin() => '/admin/dashboard',
            $request->user()?->isExecutor() => '/executor/dashboard',
            default => '/dashboard',
        });

        $middleware->web(append: [
            HandleInertiaRequests::class,
            RecordNavigationVisit::class,
        ]);

        $middleware->alias([
            'is_active' => EnsureUserIsActive::class,
            'is_admin' => EnsureUserIsAdmin::class,
            'is_executor' => EnsureUserIsExecutor::class,
            'yookassa_webhook' => VerifyYooKassaWebhook::class,
            'api.token' => AuthenticateApiToken::class,
            'kernel.token' => AuthenticateKernel::class,
            'api.ability' => EnsureApiAbility::class,
            'brief-link-headers' => BriefLinkHeaders::class,
            'public-brief-locale' => PublicBriefLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
