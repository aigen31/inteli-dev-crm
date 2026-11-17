<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\BriefStatus;
use App\Http\Controllers\Controller;
use App\Models\Brief;
use App\Models\BriefTemplate;
use App\Models\Project;
use App\Services\Briefs\BriefSchema;
use App\Services\Briefs\BriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Кабинет клиента: брифы вместо Telegram Mini App.
 */
class BriefController extends Controller
{
    public function __construct(
        private readonly BriefService $briefs,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $briefs = Brief::query()
            ->visibleTo($user)
            ->with(['template:id,title,slug,icon,estimated_minutes,schema', 'project:id,key,name'])
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 1 WHEN status = 'archived' THEN 2 ELSE 0 END")
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Client/Briefs/Index', [
            'briefs' => $briefs->map(fn (Brief $brief): array => [
                ...$brief->toListArray(),
                'is_editable' => $brief->isEditable(),
                'estimated_minutes' => $brief->template?->estimated_minutes,
                'section_count' => count($brief->sections()),
            ]),
            'stats' => [
                'open' => $briefs->filter(fn (Brief $brief): bool => $brief->status instanceof BriefStatus && $brief->status->isEditable())->count(),
                'submitted' => $briefs->where('status', BriefStatus::Submitted)->count(),
            ],
        ]);
    }

    /**
     * Клиент выбирает шаблон и заводит бриф для себя.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Client/Briefs/Create', [
            'templates' => BriefTemplate::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (BriefTemplate $template): array => $template->toLibraryArray()),
            'projects' => Project::query()
                ->whereNull('archived_at')
                ->where(fn ($query) => $query->where('client_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))
                ->orderBy('name')
                ->get(['id', 'key', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'brief_template_id' => ['required', 'uuid', 'exists:brief_templates,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $template = BriefTemplate::query()->active()->findOrFail($data['brief_template_id']);

        $brief = $this->briefs->create($template, [
            'title' => $data['title'] ?? null,
            'client_id' => $user->id,
            'project_id' => $data['project_id'] ?? null,
        ], $user);

        return redirect()
            ->route('client.briefs.show', $brief)
            ->with('success', 'Бриф создан — заполните секции.');
    }

    public function rename(Request $request, Brief $brief): RedirectResponse
    {
        $this->authorize('update', $brief);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $this->briefs->rename($brief, (string) $data['title']);

        return back()->with('success', 'Название обновлено.');
    }

    public function destroy(Brief $brief): RedirectResponse
    {
        $this->authorize('delete', $brief);

        $brief->delete();

        return redirect()
            ->route('client.briefs.index')
            ->with('success', 'Бриф удалён.');
    }

    public function show(Request $request, Brief $brief): Response
    {
        $this->authorize('view', $brief);

        $brief->load(['template', 'project:id,key,name']);

        $schema = $brief->schema();
        $answers = $brief->answers();

        return Inertia::render('Client/Briefs/Show', [
            'brief' => [
                'id' => $brief->id,
                'title' => $brief->title,
                'status' => $brief->status?->value,
                'status_label' => $brief->status?->label(),
                'progress' => $brief->progress,
                'is_editable' => $brief->isEditable(),
                'submitted_at' => $brief->submitted_at?->toIso8601String(),
                'template' => $brief->template ? [
                    'title' => $brief->template->title,
                    'summary' => $brief->template->summary,
                    'icon' => $brief->template->icon,
                    'estimated_minutes' => $brief->template->estimated_minutes,
                ] : null,
                'project' => $brief->project ? [
                    'key' => $brief->project->key,
                    'name' => $brief->project->name,
                ] : null,
            ],
            'schema' => $schema,
            'answers' => $answers,
            'successMessage' => (string) ($schema['submit']['success_message'] ?? 'Спасибо! Бриф отправлен — вернусь с ответом в течение рабочего дня.'),
            'progress' => BriefSchema::progress($schema, $answers),
        ]);
    }

    /**
     * Автосохранение из мастера: мягкая валидация, прогресс и статус.
     */
    public function save(Request $request, Brief $brief): JsonResponse
    {
        $this->authorize('fill', $brief);

        $data = $request->validate([
            'answers' => ['present', 'array'],
        ]);

        $brief = $this->briefs->saveAnswers($brief, $data['answers']);

        return response()->json([
            'progress' => $brief->progress,
            'status' => $brief->status?->value,
            'status_label' => $brief->status?->label(),
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, Brief $brief): RedirectResponse
    {
        $this->authorize('fill', $brief);

        $data = $request->validate([
            'answers' => ['present', 'array'],
        ]);

        $brief = $this->briefs->saveAnswers($brief, $data['answers']);
        $this->briefs->submit($brief, $request->user());

        return redirect()
            ->route('client.briefs.show', $brief)
            ->with('success', 'Бриф отправлен.');
    }
}
