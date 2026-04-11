<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'yookassa' => [
                'shop_id' => config('services.yookassa.shop_id') ? '••••' . substr((string) config('services.yookassa.shop_id'), -4) : null,
                'is_configured' => (bool) config('services.yookassa.shop_id'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        // Settings are managed via .env — this endpoint is a placeholder
        return back()->with('success', 'Настройки сохранены.');
    }
}
