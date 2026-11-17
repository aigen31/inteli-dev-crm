<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarIntervalController extends Controller
{
    /**
     * Завершённая работа не создаёт нагрузку: такие задачи не считаются
     * ни в календаре, ни в модалке дня. Отклонённая админом — тоже тупик.
     */
    private const NOT_WORKLOAD = [
        TaskStatus::Completed->value,
        TaskStatus::RejectedByAdmin->value,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
        ]);

        $user = $request->user();

        if (! empty($data['task_id'])) {
            $task = Task::query()->visibleTo($user)->findOrFail($data['task_id']);
            $assigneeId = $task->assignee_id;
        } else {
            $assigneeId = $this->assignee($user, $data['assignee_id'] ?? null);
        }

        $intervals = Task::query()
            ->where('assignee_id', $assigneeId)
            ->whereNotIn('status', self::NOT_WORKLOAD)
            ->where(fn ($query) => $query->whereNotNull('starts_at')->orWhereNotNull('deadline'))
            ->get(['starts_at', 'deadline'])
            ->map(fn (Task $task): array => [
                'start' => $task->starts_at?->toDateString() ?? $task->deadline?->toDateString(),
                'end' => $task->deadline?->toDateString() ?? $task->starts_at?->toDateString(),
            ])
            ->filter(fn (array $interval): bool => $interval['start'] !== null && $interval['end'] !== null)
            ->values();

        return response()->json(['intervals' => $intervals]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();

        if (! empty($data['task_id'])) {
            $context = Task::query()->visibleTo($user)->findOrFail($data['task_id']);
            $assigneeId = $context->assignee_id;
        } else {
            $assigneeId = $this->assignee($user, $data['assignee_id'] ?? null);
        }

        $date = $data['date'];
        $tasks = Task::query()
            ->visibleTo($user)
            ->where('assignee_id', $assigneeId)
            ->whereNotIn('status', self::NOT_WORKLOAD)
            ->where(function ($query) use ($date): void {
                $query->where(function ($query) use ($date): void {
                    $query->whereNotNull('starts_at')->whereNotNull('deadline')
                        ->whereDate('starts_at', '<=', $date)->whereDate('deadline', '>=', $date);
                })->orWhere(function ($query) use ($date): void {
                    $query->whereNotNull('starts_at')->whereNull('deadline')->whereDate('starts_at', $date);
                })->orWhere(function ($query) use ($date): void {
                    $query->whereNull('starts_at')->whereNotNull('deadline')->whereDate('deadline', $date);
                });
            })
            ->orderBy('starts_at')
            ->get(['id', 'title', 'status', 'starts_at', 'deadline'])
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status->value,
                'starts_at' => $task->starts_at?->toDateString(),
                'deadline' => $task->deadline?->toDateString(),
                'url' => $this->taskUrl($user, $task),
            ])
            ->values();

        return response()->json(['tasks' => $tasks]);
    }

    /**
     * Чей календарь показываем: по умолчанию свой, админ может выбрать сотрудника.
     */
    private function assignee(User $user, ?string $requested): string
    {
        if ($requested === null || $requested === '') {
            return (string) $user->id;
        }

        if ($requested !== $user->id && ! $user->isAdmin()) {
            abort(403);
        }

        return $requested;
    }

    private function taskUrl(User $user, Task $task): string
    {
        if ($user->isAdmin()) {
            return route('admin.tasks.show', $task->id);
        }

        if ($user->role === UserRole::Executor) {
            return route('executor.tasks.show', $task->id);
        }

        return route('client.tasks.show', $task->id);
    }

    public function activity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $assigneeId = $this->assignee($request->user(), $data['assignee_id'] ?? null);

        $start = Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $counts = [];

        $tasks = Task::query()
            ->where('assignee_id', $assigneeId)
            ->whereNotIn('status', self::NOT_WORKLOAD)
            ->where(fn ($query) => $query->whereNotNull('starts_at')->orWhereNotNull('deadline'))
            ->get(['starts_at', 'deadline']);

        foreach ($tasks as $task) {
            $from = $task->starts_at?->copy() ?? $task->deadline?->copy();
            $to = $task->deadline?->copy() ?? $task->starts_at?->copy();
            if ($from === null || $to === null) {
                continue;
            }
            $cursor = $from->lt($start) ? $start->copy() : $from->copy();
            $last = $to->gt($end) ? $end->copy() : $to->copy();
            while ($cursor->lte($last)) {
                $key = $cursor->toDateString();
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $cursor->addDay();
            }
        }

        return response()->json(['month' => $data['month'], 'days' => $counts]);
    }
}
