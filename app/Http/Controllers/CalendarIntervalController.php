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
}
