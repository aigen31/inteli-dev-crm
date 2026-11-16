<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Гость приходит по ссылке без языковой сессии. Текст брифа русский,
 * поэтому интерфейс по умолчанию тоже русский — иначе заголовки
 * переводились бы на английский и страница выглядела бы смешанной.
 * Если гость уже выбрал язык (сессия или локаль), уважаем его.
 */
class PublicBriefLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('locale')) {
            $request->session()->put('locale', 'ru');
        }

        return $next($request);
    }
}
