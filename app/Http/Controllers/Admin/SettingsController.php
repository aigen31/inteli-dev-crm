<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(SettingsService $settings): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'yookassa' => [
                'shop_id' => config('services.yookassa.shop_id') ? '••••' . substr((string) config('services.yookassa.shop_id'), -4) : null,
                'is_configured' => (bool) config('services.yookassa.shop_id'),
            ],
            'transfer' => $settings->transferFields(),
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'transfer_recipient' => ['nullable', 'string', 'max:150'],
            'transfer_inn' => ['nullable', 'string', 'max:20'],
            'transfer_bank' => ['nullable', 'string', 'max:150'],
            'transfer_account' => ['nullable', 'string', 'max:40'],
            'transfer_bik' => ['nullable', 'string', 'max:20'],
            'transfer_corr_account' => ['nullable', 'string', 'max:40'],
            'transfer_phone' => ['nullable', 'string', 'max:40'],
            'transfer_note' => ['nullable', 'string', 'max:500'],
        ]);

        $settings->saveTransfer($data);

        return back()->with('success', 'Реквизиты для прямого перевода сохранены.');
    }
}
