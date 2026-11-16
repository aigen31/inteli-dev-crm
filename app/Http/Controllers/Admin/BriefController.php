<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BriefStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Brief;
use App\Models\BriefTemplate;
use App\Models\Project;
use App\Models\User;
use App\Services\Briefs\BriefExportService;
use App\Services\Briefs\BriefFormatter;
use App\Services\Briefs\BriefSchema;
use App\Services\Briefs\BriefService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BriefController extends Controller
{
    public function __construct(
        private readonly BriefService $briefs,
        private readonly BriefFormatter $formatter,
        private readonly BriefExportService $export,
    ) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'open');
        $search = trim((string) $request->query('q', ''));

        $query = Brief::query()
            ->with(['template:id,title,slug,icon', 'client:id,name', 'project:id,key,name']);

        match ($status) {
            'archived' => $query->where('status', BriefStatus::Archived),
            'submitted' => $query->where('status', BriefStatus::Submitted),
            'draft' => $query->whereIn('status', [BriefStatus::Draft, BriefStatus::InProgress]),
            'all' => null,
            default => $query->whereIn('status', BriefStatus::openCases()),
        };

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (string) $request->query('client_id'));
        }

        $briefs = $query->orderByDesc('updated_at')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Briefs/Index', [
            'briefs' => $briefs->through(fn (Brief $brief): array => $brief->toListArray()),
            'filters' => [
                'status' => $status,
                'q' => $search,
                'client_id' => $request->query('client_id'),
            ],
            'stats' => [
                'total' => Brief::query()->count(),
                'submitted' => Brief::query()->where('status', BriefStatus::Submitted)->count(),
                'in_progress' => Brief::query()->whereIn('status', [BriefStatus::Draft, BriefStatus::InProgress])->count(),
                'archived' => Brief::query()->where('status', BriefStatus::Archived)->count(),
            ],
            'statuses' => collect(BriefStatus::cases())->map(fn (BriefStatus $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Briefs/Create', [
            'templates' => BriefTemplate::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(fn (BriefTemplate $template): array => $template->toLibraryArray()),
            'clients' => User::query()
                ->where('role', UserRole::Client)
                ->orderBy('name')
                ->get(['id', 'name']),
            'projects' => Project::query()
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'client_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brief_template_id' => ['required', 'uuid', 'exists:brief_templates,id'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $template = BriefTemplate::query()->findOrFail($data['brief_template_id']);

        $brief = $this->briefs->create($template, $data, $request->user());

        return redirect()
            ->route('admin.briefs.show', $brief)
            ->with('success', 'Бриф создан. Клиент увидит его в своём кабинете.');
    }

    public function show(Brief $brief): Response
    {
        $brief->load(['template', 'client:id,name,email', 'project:id,key,name', 'creator:id,name', 'revisions' => fn ($query) => $query->with('creator:id,name')]);

        return Inertia::render('Admin/Briefs/Show', [
            'brief' => [
                ...$brief->toListArray(),
                'admin_note' => $brief->admin_note,
                'created_at' => $brief->created_at?->toIso8601String(),
                'creator' => $brief->creator ? ['id' => $brief->creator->id, 'name' => $brief->creator->name] : null,
                'client' => $brief->client ? ['id' => $brief->client->id, 'name' => $brief->client->name, 'email' => $brief->client->email] : null,
            ],
            'sections' => $this->formatter->sections($brief),
            'progress' => BriefSchema::progress($brief->schema(), $brief->answers()),
            'revisions' => $brief->revisions->map(fn ($revision): array => [
                'id' => $revision->id,
                'version' => $revision->version,
                'reason' => $revision->reason,
                'author' => $revision->creator?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
            ]),
            'can' => [
                'reopen' => $brief->status === BriefStatus::Submitted,
                'archive' => $brief->status !== BriefStatus::Archived,
                'fill' => $brief->isEditable(),
            ],
        ]);
    }

    public function update(Request $request, Brief $brief): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:4000'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $brief->fill(array_filter($data, static fn (mixed $value): bool => $value !== null))->save();

        return back()->with('success', 'Бриф обновлён.');
    }

    public function reopen(Brief $brief): RedirectResponse
    {
        $this->briefs->reopen($brief, request()->user());

        return back()->with('success', 'Бриф возвращён клиенту на доработку.');
    }

    public function archive(Brief $brief): RedirectResponse
    {
        $this->briefs->archive($brief);

        return back()->with('success', 'Бриф перенесён в архив.');
    }

    public function export(Brief $brief, string $format): SymfonyResponse
    {
        $brief->load(['template', 'client', 'project']);

        return match ($format) {
            'json' => $this->export->toJson($brief),
            'md' => $this->export->toMarkdown($brief),
            'pdf' => $this->export->toPdf($brief),
            default => abort(404),
        };
    }
}
