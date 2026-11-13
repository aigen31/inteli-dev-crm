<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            return back()->withErrors(['email' => 'Ссылка недействительна или устарела. Попросите администратора создать новую.']);
        }

        $request->merge(['email' => $user->email]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => null,
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Ссылка недействительна или устарела. Попросите администратора создать новую.']);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'Аккаунт деактивирован. Обратитесь к администратору.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $target = match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isExecutor() || (! $user->hasClientDelegation() && $user->hasExecutorDelegation()) => route('executor.dashboard'),
            default => route('client.dashboard'),
        };

        return redirect($target)->with('success', 'Пароль изменён. Вы уже вошли в аккаунт.');
    }
}
