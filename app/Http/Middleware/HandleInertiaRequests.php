<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = $request->session()->get('locale') ?? $user?->locale ?? 'en';

        if (! in_array($locale, ['en', 'ru', 'zh_CN'], true)) {
            $locale = 'en';
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
                    'is_admin' => $user->isAdmin(),
                    'initials' => $this->initials($user->name),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'api_token' => fn () => $request->session()->get('api_token'),
            ],
            'locale' => $locale,
            'theme' => (function () use ($user): string {
                $theme = $user?->theme;

                if ($theme === 'xp' && ! $user?->hasFeature('xp_theme')) {
                    return 'default';
                }

                return in_array($theme, ['default', 'dark', 'xp'], true) ? $theme : 'default';
            })(),
            'features' => $user?->features ?? [],
            'locales' => [
                ['code' => 'en', 'label' => 'English', 'short' => 'EN'],
                ['code' => 'ru', 'label' => 'Русский', 'short' => 'RU'],
                ['code' => 'zh_CN', 'label' => '中文', 'short' => 'ZH'],
            ],
            'assistant' => [
                'enabled' => (bool) config('ai.enabled', true),
                'name' => 'Кики',
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }
}
