<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardLayoutController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $allowed = ['clients', 'active_tasks', 'revenue', 'templates', 'focus', 'activity', 'deadlines', 'quick_links'];

        $data = $request->validate([
            'widgets' => ['required', 'array', 'max:20'],
            'widgets.*.id' => ['required', 'string', 'distinct', Rule::in($allowed)],
            'widgets.*.w' => ['required', 'integer', 'between:1,4'],
            'widgets.*.h' => ['required', 'integer', 'between:1,4'],
        ]);

        $request->user()->update(['dashboard_layout' => $data['widgets']]);

        return response()->json(['widgets' => $data['widgets']]);
    }
}
