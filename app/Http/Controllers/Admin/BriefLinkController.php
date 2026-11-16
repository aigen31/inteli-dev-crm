<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brief;
use App\Models\BriefLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ссылки на публичное заполнение брифа: выпуск и отзыв.
 *
 * Открытый токен показывается один раз — в ответе на выпуск. Дальше в базе
 * только хэш, поэтому «посмотреть ссылку ещё раз» невозможно by design.
 */
class BriefLinkController extends Controller
{
    public function store(Request $request, Brief $brief): JsonResponse
    {
        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        if (! $brief->isEditable()) {
            abort(409, 'Бриф уже отправлен — ссылка не нужна.');
        }

        [$link, $token] = BriefLink::issue($brief, $request->user(), (int) ($data['days'] ?? 7));

        return response()->json([
            'url' => route('public.briefs.show', $token),
            'link' => $link->toAdminArray(),
            'expires_at' => $link->expires_at?->toIso8601String(),
        ]);
    }

    public function destroy(Brief $brief, BriefLink $link): RedirectResponse
    {
        abort_unless($link->brief_id === $brief->id, 404);

        if ($link->revoked_at === null && $link->used_at === null) {
            $link->forceFill(['revoked_at' => now()])->save();
        }

        return back()->with('success', 'Ссылка отозвана.');
    }
}
