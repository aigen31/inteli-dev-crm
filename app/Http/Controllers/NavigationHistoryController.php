<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NavigationHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NavigationHistoryController extends Controller
{
    public function destroy(Request $request, NavigationHistoryService $history): RedirectResponse
    {
        $history->clear($request->user());

        return back()->with('success', 'История переходов очищена.');
    }
}
