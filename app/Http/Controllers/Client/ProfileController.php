<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateProfileRequest;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request, ConsentService $consents): Response
    {
        return Inertia::render('Client/Profile/Edit', [
            'user' => $request->user()->only('id', 'name', 'email', 'phone', 'messenger_contacts'),
            'hasConsent' => $consents->hasAny($request->user()),
            'balance' => (float) $request->user()->balance,
            'balanceTransactions' => $request->user()->balanceTransactions()
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn ($transaction): array => [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'reason' => $transaction->reason,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(UpdateProfileRequest $request, ConsentService $consents): RedirectResponse
    {
        $user = $request->user();

        if (! $consents->hasAny($user) && ! $request->boolean('consent')) {
            throw ValidationException::withMessages([
                'consent' => 'Подтвердите согласие на обработку персональных данных.',
            ]);
        }

        $user->update($request->validated());

        if ($request->boolean('consent')) {
            $consents->record($user, 'client-account', $request);
        }

        return back()->with('success', 'Профиль обновлён.');
    }
}
