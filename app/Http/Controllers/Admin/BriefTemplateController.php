<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BriefTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Библиотека шаблонов брифов.
 *
 * Создание и правка схемы — задача Кики (tools create_brief_template /
 * update_brief_template): модель собирает JSON-схему и валидирует её движком.
 * Здесь остаётся удаление и обзор, чтобы не держать в админке второй редактор.
 */
class BriefTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = BriefTemplate::query()
            ->withCount('briefs')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return Inertia::render('Admin/Briefs/Templates', [
            'templates' => $templates->map(fn (BriefTemplate $template): array => [
                ...$template->toLibraryArray(),
                'briefs_count' => (int) $template->briefs_count,
                'updated_at' => $template->updated_at?->toIso8601String(),
            ]),
            'stats' => [
                'total' => $templates->count(),
                'active' => $templates->where('is_active', true)->count(),
                'used' => $templates->where('briefs_count', '>', 0)->count(),
            ],
        ]);
    }

    public function destroy(BriefTemplate $briefTemplate): RedirectResponse
    {
        $used = $briefTemplate->briefs()->count();

        if ($used > 0) {
            return back()->with('error', "Шаблон используют {$used} бриф(ов). Сначала удалите или архивируйте их.");
        }

        $title = $briefTemplate->title;
        $briefTemplate->delete();

        return back()->with('success', "Шаблон «{$title}» удалён.");
    }
}
