<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Неверный email или пароль.',
            ])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Ваш аккаунт деактивирован.',
            ]);
        }

        $request->session()->regenerate();

        $target = $user->isAdmin() ? route('admin.dashboard') : route('client.dashboard');
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended)) {
            $path = parse_url($intended, PHP_URL_PATH) ?: '/';
            $belongsToAdmin = str_starts_with($path, '/admin');

            if ($user->isAdmin() === $belongsToAdmin) {
                return redirect($intended);
            }
        }

        return redirect($target);
    }
}
