<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brief;
use App\Models\BriefLink;
use App\Services\Briefs\BriefSchema;
use App\Services\Briefs\BriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Публичное заполнение брифа по ссылке, без аккаунта.
 *
 * Доступ даёт только токен из URL: он привязан к одному брифу, истекает,
 * отзывается и гасится после отправки. Страница не показывает ни клиента,
 * ни проект — только секции и поля самого брифа.
 */
class PublicBriefController extends Controller
{
    public function __construct(
        private readonly BriefService $briefs,
    ) {}

    public function show(string $token): Response
    {
        // Гость приходит без языковой сессии: интерфейс брифа русский,
        // иначе заголовки переводились бы на английский.
        $locale = (string) session('locale', 'ru');
        Inertia::share('locale', $locale);
        app()->setLocale($locale);

        $link = $this->resolve($token);
        $brief = $link->brief()->with('template')->firstOrFail();
        $editable = $brief->isEditable() && $link->isActive();

        $schema = $brief->schema();

        return Inertia::render('Public/BriefFill', [
            'closed' => ! $editable,
            'closedReason' => match (true) {
                $brief->status?->value === 'submitted' => 'sent',
                $brief->status?->value === 'archived' => 'archived',
                $link->used_at !== null => 'sent',
                $link->revoked_at !== null => 'revoked',
                $link->expires_at->isPast() => 'expired',
                default => null,
            },
            'brief' => [
                'title' => $brief->title,
                'status_label' => $brief->status?->label(),
                'submitted_at' => $brief->submitted_at?->toIso8601String(),
                'expires_at' => $link->expires_at->toIso8601String(),
                'template' => $brief->template ? [
                    'title' => $brief->template->title,
                    'summary' => $brief->template->summary,
                    'estimated_minutes' => $brief->template->estimated_minutes,
                ] : null,
            ],
            'schema' => $editable ? $schema : null,
            'answers' => $editable ? $brief->answers() : [],
            'endpoints' => [
                'save' => route('public.briefs.save', $token),
                'submit' => route('public.briefs.submit', $token),
            ],
            'successMessage' => (string) ($schema['submit']['success_message'] ?? 'Спасибо! Бриф отправлен — вернусь с ответом в течение рабочего дня.'),
            'progress' => $editable ? BriefSchema::progress($schema, $brief->answers()) : 100,
        ]);
    }

    public function save(Request $request, string $token): JsonResponse
    {
        $link = $this->resolve($token, activeOnly: true);
        $brief = $link->brief()->firstOrFail();

        abort_unless($brief->isEditable(), 409, 'Бриф уже отправлен.');

        $data = $request->validate([
            'answers' => ['present', 'array'],
        ]);

        $brief = $this->briefs->saveAnswers($brief, $data['answers']);
        $link->touchUsage($request->ip());

        return response()->json([
            'progress' => $brief->progress,
            'status' => $brief->status?->value,
            'status_label' => $brief->status?->label(),
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $link = $this->resolve($token, activeOnly: true);
        $brief = $link->brief()->firstOrFail();

        $data = $request->validate([
            'answers' => ['present', 'array'],
        ]);

        $brief = $this->briefs->saveAnswers($brief, $data['answers']);
        // Автора у публичной отправки нет: ревизия помечается submitted_public,
        // а задачу на разбор создаёт владелец брифа.
        $this->briefs->submit($brief, null);

        $link->touchUsage($request->ip());

        return redirect()
            ->route('public.briefs.show', $token)
            ->with('success', 'Бриф отправлен.');
    }

    private function resolve(string $token, bool $activeOnly = false): BriefLink
    {
        $link = BriefLink::findActive($token);

        if ($link === null && ! $activeOnly) {
            $link = BriefLink::query()->where('token_hash', BriefLink::hash($token))->first();
        }

        abort_if($link === null, 404);

        return $link;
    }
}
