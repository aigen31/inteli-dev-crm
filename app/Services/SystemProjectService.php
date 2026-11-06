<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeedbackItem;
use Illuminate\Support\Arr;

/**
 * Hard-coded, always-available service projects.
 *
 * They intentionally do not live in the projects table: every authenticated
 * user gets access, and their lifecycle is owned by the product, not by a
 * client or project manager.
 */
class SystemProjectService
{
    public const FEEDBACK = 'feedback';

    public const BUGS = 'bugs';

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return [
            $this->definition(
                key: self::FEEDBACK,
                code: 'FDB',
                name: 'Обратная связь',
                description: 'Идеи, улучшения и пожелания к CRM. Голосуйте за то, что важно именно вам.',
                type: self::FEEDBACK,
                color: 'violet',
                icon: 'light-bulb',
                itemLabel: 'предложение',
                statuses: [
                    ['value' => 'open', 'label' => 'Новое', 'tone' => 'sky'],
                    ['value' => 'review', 'label' => 'На рассмотрении', 'tone' => 'peach'],
                    ['value' => 'planned', 'label' => 'Запланировано', 'tone' => 'violet'],
                    ['value' => 'in_progress', 'label' => 'В работе', 'tone' => 'violet'],
                    ['value' => 'done', 'label' => 'Сделано', 'tone' => 'sage'],
                    ['value' => 'declined', 'label' => 'Отклонено', 'tone' => 'ink'],
                ],
                categories: [
                    ['value' => 'idea', 'label' => 'Идея'],
                    ['value' => 'improvement', 'label' => 'Улучшение'],
                    ['value' => 'integration', 'label' => 'Интеграция'],
                    ['value' => 'other', 'label' => 'Другое'],
                ],
                severities: [],
            ),
            $this->definition(
                key: self::BUGS,
                code: 'BUG',
                name: 'Баги',
                description: 'Сообщите о поломке: что произошло, как повторить и что должно было быть.',
                type: self::BUGS,
                color: 'peach',
                icon: 'bug',
                itemLabel: 'баг',
                statuses: [
                    ['value' => 'open', 'label' => 'Новый', 'tone' => 'sky'],
                    ['value' => 'confirmed', 'label' => 'Подтверждён', 'tone' => 'peach'],
                    ['value' => 'in_progress', 'label' => 'В работе', 'tone' => 'violet'],
                    ['value' => 'fixed', 'label' => 'Исправлен', 'tone' => 'sage'],
                    ['value' => 'closed', 'label' => 'Закрыт', 'tone' => 'ink'],
                    ['value' => 'declined', 'label' => 'Отклонён', 'tone' => 'ink'],
                ],
                categories: [],
                severities: [
                    ['value' => 'low', 'label' => 'Низкая', 'tone' => 'sage'],
                    ['value' => 'medium', 'label' => 'Средняя', 'tone' => 'peach'],
                    ['value' => 'high', 'label' => 'Высокая', 'tone' => 'warning'],
                    ['value' => 'critical', 'label' => 'Критичная', 'tone' => 'danger'],
                ],
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return Arr::first($this->all(), fn (array $project): bool => $project['key'] === $key);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function summaries(): array
    {
        $counts = FeedbackItem::query()
            ->selectRaw('type, status, count(*) as aggregate')
            ->groupBy('type', 'status')
            ->get()
            ->groupBy('type');

        return array_map(function (array $project) use ($counts): array {
            $rows = $counts->get($project['type'], collect())->pluck('aggregate', 'status');
            $terminal = $this->terminalStatuses($project);

            return [
                ...$project,
                'total' => (int) $rows->sum(),
                'open' => (int) $rows->except($terminal)->sum(),
                'done' => (int) $rows->only($terminal)->sum(),
                'planned' => (int) $rows->only(['planned', 'in_progress'])->sum(),
            ];
        }, $this->all());
    }

    /**
     * @param  array<string, mixed>  $project
     * @return list<string>
     */
    public function statusValues(array $project): array
    {
        return array_column($project['statuses'], 'value');
    }

    /**
     * @param  array<string, mixed>  $project
     * @return list<string>
     */
    public function terminalStatuses(array $project): array
    {
        return array_values(array_intersect(
            $this->statusValues($project),
            ['done', 'fixed', 'closed', 'declined'],
        ));
    }

    /**
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>|null
     */
    public function status(array $project, string $value): ?array
    {
        return Arr::first($project['statuses'], fn (array $status): bool => $status['value'] === $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(
        string $key,
        string $code,
        string $name,
        string $description,
        string $type,
        string $color,
        string $icon,
        string $itemLabel,
        array $statuses,
        array $categories,
        array $severities,
    ): array {
        return [
            'key' => $key,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'color' => $color,
            'icon' => $icon,
            'item_label' => $itemLabel,
            'statuses' => $statuses,
            'categories' => $categories,
            'severities' => $severities,
            'url' => route('system-projects.show', ['project' => $key]),
        ];
    }
}
