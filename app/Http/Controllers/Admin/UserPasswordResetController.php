<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

class UserPasswordResetController extends Controller
{
    public function store(User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $token = Password::broker()->createToken($user);
        $link = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        return back()
            ->with('reset_link', $link)
            ->with('success', 'Ссылка для сброса пароля создана.');
    }
}
