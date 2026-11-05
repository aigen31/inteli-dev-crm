<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ProjectInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InviteController extends Controller
{
    public function show(string $token, ProjectInviteService $invites): Response
    {
        $invite = $invites->findUsable($token);

        if ($invite === null) {
            return Inertia::render('Invite/Invalid');
        }

        return Inertia::render('Invite/Register', [
            'token' => $token,
            'project' => [
                'name' => $invite->project->name,
                'key' => $invite->project->key,
            ],
            'role' => $invite->role->label(),
            'role_hint' => $invite->role->hint(),
            'email' => $invite->email,
        ]);
    }

    public function store(Request $request, string $token, ProjectInviteService $invites): RedirectResponse
    {
        $invite = $invites->findUsable($token);
        abort_if($invite === null, 410, 'Приглашение недействительно или истекло.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'consent' => ['accepted'],
            'privacy_ack' => ['accepted'],
        ]);

        $user = $invites->accept($invite, $data, $request);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard')
            ->with('success', 'Добро пожаловать! Вы добавлены в проект «'.$invite->project->name.'».');
    }
}
