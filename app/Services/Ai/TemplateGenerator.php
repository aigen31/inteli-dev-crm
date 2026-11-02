<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\HtmlSanitizer;

/**
 * Deterministic task-template draft builder.
 *
 * It does not call an OpenAI-compatible API and does not need model settings.
 * The assistant kernel is the only place where model calls live.
 */
class TemplateGenerator
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @return array{name: string, summary: string, description: string, checklist: list<string>, default_priority: string, default_due_days: int|null, source: string, warning: string|null}
     */
    public function generate(string $brief): array
    {
        $brief = trim($brief);

        if ($brief === '') {
            throw new \InvalidArgumentException('Опишите, для чего нужен шаблон.');
        }

        return $this->draft($brief);
    }

    /**
     * @return array{name: string, summary: string, description: string, checklist: list<string>, default_priority: string, default_due_days: int|null, source: string, warning: string|null}
     */
    private function draft(string $brief): array
    {
        $firstSentence = trim((string) preg_split('/[.!?\n]/u', $brief)[0]);
        $name = mb_substr($firstSentence !== '' ? $firstSentence : $brief, 0, 80);

        $description = '<p>'.e($brief).'</p>'
            .'<p><strong>Что уточнить перед стартом:</strong></p>'
            .'<ul><li>ожидаемый результат и формат;</li><li>что предоставляет клиент;</li><li>критерии готовности.</li></ul>';

        return [
            'name' => $name,
            'summary' => mb_substr($brief, 0, 160),
            'description' => $this->sanitizer->sanitize($description),
            'checklist' => [
                'Уточнить ожидания и исходные материалы',
                'Согласовать объём и критерии готовности',
                'Выполнить работу',
                'Передать результат и собрать обратную связь',
            ],
            'default_priority' => 'medium',
            'default_due_days' => 3,
            'source' => 'draft',
            'warning' => 'Это черновик по описанию. Проверьте формулировки и дополните детали.',
        ];
    }
}
