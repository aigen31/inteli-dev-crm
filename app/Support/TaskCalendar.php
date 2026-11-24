<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Общая логика календаря нагрузки: какие дни занимает задача и как
 * отфильтровать список по выбранной дате.
 */
final class TaskCalendar
{
    /**
     * @param  iterable<int, object{starts_at: mixed, deadline: mixed, status: mixed}>  $tasks
     * @return list<array{start: string, end: string, status: string|null}>
     */
    public static function ranges(iterable $tasks): array
    {
        $ranges = [];

        foreach ($tasks as $task) {
            $start = $task->starts_at?->toDateString() ?? $task->deadline?->toDateString();
            $end = $task->deadline?->toDateString() ?? $task->starts_at?->toDateString();

            if ($start === null || $end === null) {
                continue;
            }

            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            $ranges[] = [
                'start' => $start,
                'end' => $end,
                'status' => $task->status?->value,
            ];
        }

        return $ranges;
    }

    public static function applyDate(Builder $query, string $date): void
    {
        $query->where(function (Builder $query) use ($date): void {
            $query->where(function (Builder $query) use ($date): void {
                $query->whereNotNull('starts_at')->whereNotNull('deadline')
                    ->whereDate('starts_at', '<=', $date)->whereDate('deadline', '>=', $date);
            })->orWhere(function (Builder $query) use ($date): void {
                $query->whereNotNull('starts_at')->whereNull('deadline')->whereDate('starts_at', $date);
            })->orWhere(function (Builder $query) use ($date): void {
                $query->whereNull('starts_at')->whereNotNull('deadline')->whereDate('deadline', $date);
            });
        });
    }
}
