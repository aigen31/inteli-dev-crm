<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SidebarPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:40'],
            'items.*.key' => ['required', 'string', 'max:40'],
            'items.*.hidden' => ['required', 'boolean'],
        ]);

        $request->user()->update(['sidebar_preferences' => $data['items']]);

        return back()->with('success', 'Меню рабочего пространства сохранено.');
    }
}
