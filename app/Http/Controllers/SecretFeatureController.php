<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SecretFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SecretFeatureController extends Controller
{
    public function unlock(Request $request, SecretFeatureService $features): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120'],
        ]);

        $feature = $features->unlock($request->user(), $data['code']);

        if ($feature === null) {
            throw ValidationException::withMessages([
                'code' => 'Код не подошёл. Проверьте раскладку и попробуйте ещё раз.',
            ]);
        }

        return back()->with('success', 'Секретная функция разблокирована.');
    }

    public function theme(Request $request, SecretFeatureService $features): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(['default', 'xp'])],
        ]);

        abort_unless($features->has($request->user(), SecretFeatureService::XP_THEME), 403);

        $request->user()->update(['theme' => $data['theme']]);

        return back();
    }
}
