<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApiAbility;
use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiTokenController extends Controller
{
    public function store(Request $request, ApiTokenService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_column(ApiAbility::cases(), 'value'))],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $result = $service->create(
            $request->user(),
            $data['name'],
            $data['abilities'],
            $data['expires_in_days'] ?? null,
        );

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'api'])
            ->with('success', 'API-токен создан. Скопируйте его сейчас — больше он не покажется.')
            ->with('api_token', [
                'name' => $result['token']->name,
                'plain' => $result['plain'],
            ]);
    }

    public function destroy(ApiToken $apiToken, ApiTokenService $service): RedirectResponse
    {
        abort_unless($apiToken->user_id === request()->user()->id, 403);

        $service->revoke($apiToken);

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'api'])
            ->with('success', 'Токен отозван.');
    }
}
