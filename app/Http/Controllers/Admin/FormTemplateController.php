<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FormTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = FormTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (FormTemplate $template) => [
                ...$template->toLibraryArray(),
                'is_active' => $template->is_active,
            ]);

        return Inertia::render('Admin/Forms/Index', [
            'templates' => $templates,
            'stats' => [
                'total' => $templates->count(),
                'active' => $templates->where('is_active', true)->count(),
                'fields' => $templates->sum('field_count'),
                'usage' => $templates->sum('usage_count'),
            ],
        ]);
    }

    public function toggle(FormTemplate $formTemplate): RedirectResponse
    {
        $formTemplate->update(['is_active' => ! $formTemplate->is_active]);

        return back()->with('success', $formTemplate->is_active ? 'Форма включена.' : 'Форма выключена.');
    }
}
