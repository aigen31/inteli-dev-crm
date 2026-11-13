<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $email = strtolower(trim((string) $request->input('email')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors([
                'email' => 'Неверный email или пароль.',
            ])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Ваш аккаунт деактивирован.',
            ]);
        }

        $request->session()->regenerate();

        $target = match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isExecutor() || (! $user->hasClientDelegation() && $user->hasExecutorDelegation()) => route('executor.dashboard'),
            default => route('client.dashboard'),
        };
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended)) {
            $path = parse_url($intended, PHP_URL_PATH) ?: '/';
            $belongsToAdmin = str_starts_with($path, '/admin');
            $belongsToExecutor = str_starts_with($path, '/executor');

            if (($user->isAdmin() && $belongsToAdmin) || ($user->isExecutor() && $belongsToExecutor) || (! $user->isAdmin() && ! $user->isExecutor() && ! $belongsToAdmin && ! $belongsToExecutor)) {
                return redirect($intended);
            }
        }

        return redirect($target);
    }
}
