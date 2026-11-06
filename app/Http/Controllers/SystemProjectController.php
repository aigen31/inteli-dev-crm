<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackItem;
use App\Models\FeedbackVote;
use App\Services\SystemProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemProjectController extends Controller
{
    public function __construct(
        private readonly SystemProjectService $projects,
    ) {}

    public function show(Request $request, string $project): Response
    {
        $definition = $this->definition($project);
        $user = $request->user();

        $status = (string) $request->query('status', '');
        $status = in_array($status, $this->projects->statusValues($definition), true) ? $status : '';
        $sort = $request->query('sort') === 'new' ? 'new' : 'top';
        $search = trim((string) $request->query('q', ''));

        $items = FeedbackItem::query()
            ->where('type', $definition['type'])
            ->with('user:id,name')
            ->withCount('votes')
            ->withExists(['votes as voted_by_me' => fn ($query) => $query->where('user_id', $user->id)])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($sort === 'top', fn ($query) => $query->orderByDesc('votes_count')->orderByDesc('created_at'))
            ->when($sort === 'new', fn ($query) => $query->orderByDesc('created_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FeedbackItem $item): array => $this->itemPayload($item, $definition));

        $counts = FeedbackItem::query()
            ->where('type', $definition['type'])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $counts->sum(),
            'open' => (int) $counts->except($this->projects->terminalStatuses($definition))->sum(),
            'done' => (int) $counts->only($this->projects->terminalStatuses($definition))->sum(),
            'planned' => (int) $counts->only(['planned', 'in_progress'])->sum(),
        ];

        return Inertia::render('SystemProjects/Show', [
            'project' => $definition,
            'items' => $items,
            'filters' => [
                'status' => $status,
                'sort' => $sort,
                'q' => $search,
            ],
            'statusCounts' => collect($definition['statuses'])->mapWithKeys(
                fn (array $status): array => [$status['value'] => (int) ($counts[$status['value']] ?? 0)],
            )->all(),
            'stats' => $stats,
            'canManage' => $user->isAdmin(),
        ]);
    }

    public function store(Request $request, string $project): RedirectResponse
    {
        $definition = $this->definition($project);
        $statusValues = $this->projects->statusValues($definition);
        $categoryValues = array_column($definition['categories'], 'value');
        $severityValues = array_column($definition['severities'], 'value');

        $rules = [
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'category' => $definition['type'] === SystemProjectService::FEEDBACK
                ? ['required', Rule::in($categoryValues)]
                : ['nullable'],
            'severity' => $definition['type'] === SystemProjectService::BUGS
                ? ['required', Rule::in($severityValues)]
                : ['nullable'],
            'steps' => $definition['type'] === SystemProjectService::BUGS
                ? ['required', 'string', 'min:10', 'max:5000']
                : ['nullable', 'string', 'max:5000'],
            'expected' => $definition['type'] === SystemProjectService::BUGS
                ? ['required', 'string', 'min:5', 'max:5000']
                : ['nullable', 'string', 'max:5000'],
            'actual' => $definition['type'] === SystemProjectService::BUGS
                ? ['required', 'string', 'min:5', 'max:5000']
                : ['nullable', 'string', 'max:5000'],
            'environment' => ['nullable', 'string', 'max:160'],
        ];

        $data = $request->validate($rules);

        FeedbackItem::create([
            'user_id' => $request->user()->id,
            'type' => $definition['type'],
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'category' => $definition['type'] === SystemProjectService::FEEDBACK ? $data['category'] : null,
            'severity' => $definition['type'] === SystemProjectService::BUGS ? $data['severity'] : null,
            'steps' => $definition['type'] === SystemProjectService::BUGS ? trim($data['steps']) : null,
            'expected' => $definition['type'] === SystemProjectService::BUGS ? trim($data['expected']) : null,
            'actual' => $definition['type'] === SystemProjectService::BUGS ? trim($data['actual']) : null,
            'environment' => filled($data['environment'] ?? null) ? trim($data['environment']) : null,
            'status' => $statusValues[0],
        ]);

        return redirect()
            ->route('system-projects.show', ['project' => $definition['key']])
            ->with('success', 'Спасибо! Отправили в работу.');
    }

    public function vote(Request $request, string $project, FeedbackItem $item): JsonResponse
    {
        $definition = $this->definition($project);
        abort_unless($item->type === $definition['type'], 404);

        $user = $request->user();

        $voted = DB::transaction(function () use ($item, $user): bool {
            $vote = FeedbackVote::query()
                ->where('feedback_item_id', $item->id)
                ->where('user_id', $user->id)
                ->first();

            if ($vote !== null) {
                $vote->delete();

                return false;
            }

            FeedbackVote::create([
                'feedback_item_id' => $item->id,
                'user_id' => $user->id,
            ]);

            return true;
        });

        return response()->json([
            'votes' => $item->votes()->count(),
            'voted' => $voted,
        ]);
    }

    public function updateStatus(Request $request, string $project, FeedbackItem $item): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $definition = $this->definition($project);
        abort_unless($item->type === $definition['type'], 404);

        $data = $request->validate([
            'status' => ['required', Rule::in($this->projects->statusValues($definition))],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? $item->admin_note,
            'resolved_at' => in_array($data['status'], $this->projects->terminalStatuses($definition), true)
                ? ($item->resolved_at ?? now())
                : null,
        ]);

        return back()->with('success', 'Статус обновлён.');
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private function itemPayload(FeedbackItem $item, array $project): array
    {
        $status = $this->projects->status($project, $item->status);
        $category = collect($project['categories'])->firstWhere('value', $item->category);
        $severity = collect($project['severities'])->firstWhere('value', $item->severity);

        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'category' => $category,
            'severity' => $severity,
            'steps' => $item->steps,
            'expected' => $item->expected,
            'actual' => $item->actual,
            'environment' => $item->environment,
            'status' => $item->status,
            'status_label' => $status['label'] ?? $item->status,
            'status_tone' => $status['tone'] ?? 'ink',
            'votes' => (int) ($item->votes_count ?? 0),
            'voted' => (bool) ($item->voted_by_me ?? false),
            'admin_note' => $item->admin_note,
            'author' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name] : null,
            'created_at' => $item->created_at?->toIso8601String(),
            'resolved_at' => $item->resolved_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $project): array
    {
        return $this->projects->find($project) ?? abort(404);
    }
}
