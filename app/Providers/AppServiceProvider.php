<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Relation::enforceMorphMap([
            'client' => User::class,
            'task' => Task::class,
            'invoice' => Invoice::class,
        ]);

        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getKey() ?? $request->ip();

            return Limit::perMinute(120)->by('api:'.$key);
        });

        RateLimiter::for('assistant', function (Request $request): Limit {
            return Limit::perMinute(30)->by('assistant:'.($request->user()?->getKey() ?? $request->ip()));
        });
    }
}
