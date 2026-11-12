<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarIntervalController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
        ]);

        $user = $request->user();
        $assigneeId = $data['assignee_id'] ?? null;

        if (! empty($data['task_id'])) {
            $task = Task::query()->visibleTo($user)->findOrFail($data['task_id']);
            $assigneeId = $task->assignee_id;
        } elseif (! $user->isAdmin()) {
            abort(403);
        }

        if ($assigneeId === null) {
            return response()->json(['intervals' => []]);
        }

        $intervals = Task::query()
            ->where('assignee_id', $assigneeId)
            ->where(fn ($query) => $query->whereNotNull('starts_at')->orWhereNotNull('deadline'))
            ->get(['starts_at', 'deadline'])
            ->map(fn (Task $task): array => [
                'start' => $task->starts_at?->toDateString() ?? $task->deadline?->toDateString(),
                'end' => $task->deadline?->toDateString() ?? $task->starts_at?->toDateString(),
            ])
            ->filter(fn (array $interval): bool => $interval['start'] !== null && $interval['end'] !== null)
            ->unique(fn (array $interval): string => $interval['start'].'|'.$interval['end'])
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
        $assigneeId = $data['assignee_id'] ?? null;

        if (! empty($data['task_id'])) {
            $context = Task::query()->visibleTo($user)->findOrFail($data['task_id']);
            $assigneeId = $context->assignee_id;
        } elseif (! $user->isAdmin()) {
            abort(403);
        }

        if ($assigneeId === null) {
            return response()->json(['tasks' => []]);
        }

        $date = $data['date'];
        $tasks = Task::query()
            ->visibleTo($user)
            ->where('assignee_id', $assigneeId)
            ->where(function ($query) use ($date): void {
                $query->where(function ($query) use ($date): void {
                    $query->whereNotNull('starts_at')->whereNotNull('deadline')
                        ->where('starts_at', '<=', $date)->where('deadline', '>=', $date);
                })->orWhere(function ($query) use ($date): void {
                    $query->whereNotNull('starts_at')->whereNull('deadline')->where('starts_at', $date);
                })->orWhere(function ($query) use ($date): void {
                    $query->whereNull('starts_at')->whereNotNull('deadline')->where('deadline', $date);
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
            ])
            ->values();

        return response()->json(['tasks' => $tasks]);
    }
}
