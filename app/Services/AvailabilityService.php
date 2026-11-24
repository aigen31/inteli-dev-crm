<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\TaskCalendar;
use Illuminate\Support\Carbon;

/**
 * Публичная занятость владельца для виджета «Приём заявок» на сайте.
 *
 * Считает только агрегаты: проекты и количество задач по дням. Ни названий
 * задач, ни клиентов наружу не отдаём.
 */
final class AvailabilityService
{
    /** День считается занятым, когда задач больше этого числа. */
    public const BUSY_AFTER = 4;

    private const HORIZON_DAYS = 30;

    private const SHOWN_DAYS = 14;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $today = Carbon::today();
        $horizonEnd = $today->copy()->addDays(self::HORIZON_DAYS - 1);

        $ranges = TaskCalendar::ranges(
            Task::query()
                ->whereNotNull('assignee_id')
                ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::RejectedByAdmin->value])
                ->where(function ($query) use ($today, $horizonEnd): void {
                    $query->where(function ($query) use ($today, $horizonEnd): void {
                        $query->whereNotNull('starts_at')->whereDate('starts_at', '<=', $horizonEnd)
                            ->where(function ($query) use ($today): void {
                                $query->whereNull('deadline')->orWhereDate('deadline', '>=', $today);
                            });
                    })->orWhere(function ($query) use ($today, $horizonEnd): void {
                        $query->whereNull('starts_at')->whereNotNull('deadline')
                            ->whereDate('deadline', '>=', $today)->whereDate('deadline', '<=', $horizonEnd);
                    });
                })
                ->get(['starts_at', 'deadline', 'status'])
        );

        $counts = [];

        foreach ($ranges as $range) {
            $cursor = Carbon::parse($range['start'])->max($today)->copy();
            $end = Carbon::parse($range['end'])->min($horizonEnd)->copy();

            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $cursor->addDay();
            }
        }

        $days = [];
        $nextFree = null;

        for ($offset = 0; $offset < self::HORIZON_DAYS; $offset++) {
            $date = $today->copy()->addDays($offset);
            $key = $date->toDateString();
            $load = $counts[$key] ?? 0;
            $workday = $date->isWeekday();
            $busy = $load > self::BUSY_AFTER;

            if ($nextFree === null && $workday && ! $busy) {
                $nextFree = $date->copy();
            }

            if ($offset < self::SHOWN_DAYS) {
                $days[] = [
                    'date' => $key,
                    'weekday' => $date->isoWeekday(),
                    'load' => $load,
                    'busy' => $busy,
                    'workday' => $workday,
                ];
            }
        }

        $activeProjects = Project::query()
            ->whereNull('archived_at')
            ->where('status', ProjectStatus::Active->value)
            ->count();

        $status = 'available';

        if ($nextFree === null) {
            $status = 'full';
        } elseif ($nextFree->gt($today->copy()->addDays(7)) || $activeProjects >= 5) {
            $status = 'busy';
        }

        return [
            'status' => $status,
            'active_projects' => $activeProjects,
            'busy_after' => self::BUSY_AFTER,
            'next_free_slot' => $nextFree?->toDateString(),
            'next_free_weekday' => $nextFree?->isoWeekday(),
            'days' => $days,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
