<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FileAttachment;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use App\Services\Reports\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $reports = TaskReport::query()
            ->with(['author:id,name', 'recipient:id,name,email', 'task:id,title,project_id', 'task.project:id,key,name', 'screenshot', 'items.screenshot'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where(fn ($q) => $q->where('recipient_id', $user->id)->orWhere('author_id', $user->id)))
            ->when($user->isAdmin() && $request->input('recipient_id'), fn ($query, $id) => $query->where('recipient_id', $id))
            ->when($request->input('task_id'), fn ($query, $id) => $query->where('task_id', $id))
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest('sent_at')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TaskReport $report): array => $this->payload($report));

        if ($user->isAdmin()) {
            return Inertia::render('Admin/Reports/Index', [
                'reports' => $reports,
                'filters' => $request->only('recipient_id', 'task_id', 'unread'),
                'recipients' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
                'counts' => [
                    'all' => TaskReport::query()->count(),
                    'unread' => TaskReport::query()->whereNull('read_at')->count(),
                ],
            ]);
        }

        return Inertia::render('Client/Reports/Index', [
            'reports' => $reports,
            'unread' => TaskReport::query()->where('recipient_id', $user->id)->whereNull('read_at')->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TaskReport::class);

        $user = $request->user();

        return Inertia::render('Admin/Reports/Create', [
            'tasks' => $this->availableTasks($user),
            'recipients' => $this->availableRecipients($user),
            'preselectedTask' => $request->query('task_id'),
            'layout' => $this->layoutFor($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TaskReport::class);

        $data = $request->validate([
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'recipient_id' => ['required', 'uuid', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:160'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.summary' => ['required', 'string', 'min:3', 'max:5000'],
            'items.*.test_url' => ['nullable', 'url', 'max:500'],
            'items.*.screenshot' => ['nullable', 'image', 'max:10240'],
        ]);

        $this->assertTaskAccess($request->user(), $data['task_id'] ?? null);

        $report = app(ReportService::class)->create($request->user(), $data);

        return redirect()->route('reports.show', $report)
            ->with('success', 'Отчёт отправлен получателю.');
    }

    public function edit(Request $request, TaskReport $report): Response
    {
        $this->authorize('update', $report);

        $report->load(['items.screenshot', 'task:id,title,project_id', 'recipient:id,name,email']);
        $payload = $this->payload($report);

        return Inertia::render('Admin/Reports/Create', [
            'report' => $payload,
            'tasks' => $this->availableTasks($request->user()),
            'recipients' => $this->availableRecipients($request->user()),
            'preselectedTask' => null,
            'layout' => $this->layoutFor($request->user()),
        ]);
    }

    public function update(Request $request, TaskReport $report): RedirectResponse
    {
        $this->authorize('update', $report);

        $data = $request->validate([
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'recipient_id' => ['required', 'uuid', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:160'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'uuid'],
            'items.*.summary' => ['required', 'string', 'min:3', 'max:5000'],
            'items.*.test_url' => ['nullable', 'url', 'max:500'],
            'items.*.screenshot' => ['nullable', 'image', 'max:10240'],
        ]);

        $this->assertTaskAccess($request->user(), $data['task_id'] ?? null);

        app(ReportService::class)->update($report, $request->user(), $data);

        return redirect()->route('reports.show', $report)->with('success', 'Отчёт обновлён.');
    }

    public function destroy(Request $request, TaskReport $report): RedirectResponse
    {
        $this->authorize('delete', $report);
        app(ReportService::class)->delete($report);

        return redirect()
            ->route(match (true) {
                $request->user()->isAdmin() => 'admin.reports.index',
                $request->user()->role === UserRole::Executor => 'executor.reports.index',
                default => 'client.reports.index',
            })
            ->with('success', 'Отчёт удалён.');
    }

    public function show(Request $request, TaskReport $report): Response
    {
        $this->authorize('view', $report);

        if ($report->recipient_id === $request->user()->id) {
            $report->markRead();
        }

        $report->load(['author:id,name', 'recipient:id,name,email', 'task:id,title,project_id', 'task.project:id,key,name', 'screenshot', 'items.screenshot']);

        $payload = $this->payload($report);

        return $request->user()->isAdmin()
            ? Inertia::render('Admin/Reports/Show', ['report' => $payload])
            : Inertia::render('Client/Reports/Show', ['report' => $payload]);
    }

    public function exportExcel(Request $request, TaskReport $report, ReportExportService $exports): BinaryFileResponse
    {
        $this->authorize('view', $report);

        return $exports->excel($report);
    }

    public function exportPdf(Request $request, TaskReport $report, ReportExportService $exports): HttpResponse
    {
        $this->authorize('view', $report);

        return $exports->pdf($report);
    }

    public function screenshot(Request $request, TaskReport $report): StreamedResponse
    {
        $this->authorize('view', $report);

        $itemId = trim((string) $request->query('item', ''));
        $fileId = $itemId !== ''
            ? $report->items()->whereKey($itemId)->value('screenshot_id')
            : $report->screenshot_id;

        $file = $fileId !== null ? FileAttachment::query()->find($fileId) : null;

        abort_if($file === null, 404);

        return Storage::disk('local')->response($file->stored_path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function taskUrl(TaskReport $report): ?string
    {
        if ($report->task === null) {
            return null;
        }

        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        if ($user->isAdmin()) {
            return route('admin.tasks.show', $report->task);
        }

        return $report->task->client_id === $user->id
            ? route('client.tasks.show', $report->task)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return list<array<string, mixed>>
     */
    private function availableTasks(User $user): array
    {
        return Task::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where(fn ($q) => $q->where('assignee_id', $user->id)->orWhere('client_id', $user->id)))
            ->with('project:id,key,name')
            ->latest()
            ->limit(200)
            ->get(['id', 'title', 'project_id', 'number'])
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'reference' => $task->project ? $task->project->key.'-'.$task->number : null,
            ])
            ->all();
    }

    /**
     * Получатели отчёта: админ видит всех, обычный пользователь — админов
     * и вторую сторону своих задач.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function availableRecipients(User $user)
    {
        $query = User::query()->where('is_active', true);

        if (! $user->isAdmin()) {
            $partnerIds = Task::query()
                ->where(fn ($q) => $q->where('assignee_id', $user->id)->orWhere('client_id', $user->id))
                ->get(['client_id', 'assignee_id'])
                ->flatMap(fn (Task $task): array => [$task->client_id, $task->assignee_id])
                ->filter(fn (?string $id): bool => $id !== null && $id !== $user->id)
                ->unique()
                ->values()
                ->all();

            $query->where(fn ($q) => $q->whereIn('id', $partnerIds)->orWhere('role', 'admin'));
        }

        return $query->orderBy('name')->get(['id', 'name', 'email', 'role']);
    }

    private function layoutFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => 'admin',
            $user->role === UserRole::Executor => 'executor',
            default => 'client',
        };
    }

    private function assertTaskAccess(User $user, ?string $taskId): void
    {
        if ($user->isAdmin() || $taskId === null) {
            return;
        }

        $task = Task::query()->find($taskId);

        abort_if(
            $task === null || ($task->assignee_id !== $user->id && $task->client_id !== $user->id),
            403,
        );
    }

    public function payload(TaskReport $report): array
    {
        $items = $report->relationLoaded('items')
            ? $report->items
            : $report->items()->with('screenshot')->get();

        $first = $items->first();

        $sentAt = $report->sent_at ?? $report->created_at;

        return [
            'id' => $report->id,
            'author_id' => $report->author_id,
            'recipient_id' => $report->recipient_id,
            'can_edit' => request()->user()?->can('update', $report) ?? false,
            'title' => $report->title,
            'display_title' => $report->title ?: ($sentAt ? 'Отчёт от '.$sentAt->format('d.m.Y') : 'Отчёт'),
            'summary' => $first?->content ?? $report->content,
            'test_url' => $first?->test_url ?? $report->test_url,
            'items' => $items->map(fn ($item): array => [
                'id' => $item->id,
                'summary' => $item->content,
                'test_url' => $item->test_url,
                'has_screenshot' => $item->screenshot_id !== null,
                'screenshot_url' => $item->screenshot_id !== null
                    ? route('reports.screenshot', ['report' => $report, 'item' => $item->id])
                    : null,
            ])->values()->all(),
            'has_screenshot' => $items->contains(fn ($item): bool => $item->screenshot_id !== null),
            'screenshot_url' => $items->contains(fn ($item): bool => $item->screenshot_id !== null)
                ? route('reports.screenshot', ['report' => $report, 'item' => $items->firstWhere('screenshot_id', '!=', null)?->id])
                : null,
            'task' => $report->task ? [
                'id' => $report->task->id,
                'title' => $report->task->title,
                'reference' => $report->task->project ? $report->task->project->key.'-'.$report->task->number : null,
                'project' => $report->task->project?->name,
                'url' => $this->taskUrl($report),
            ] : null,
            'author' => $report->author ? ['id' => $report->author->id, 'name' => $report->author->name] : null,
            'recipient' => $report->recipient ? ['id' => $report->recipient->id, 'name' => $report->recipient->name, 'email' => $report->recipient->email] : null,
            'sent_at' => $report->sent_at?->toIso8601String() ?? $report->created_at?->toIso8601String(),
            'read_at' => $report->read_at?->toIso8601String(),
        ];
    }
}
