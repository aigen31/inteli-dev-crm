<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\HtmlSanitizer;
use Illuminate\Support\Str;

class TemplateGenerator
{
    public function __construct(
        private readonly OpenAiClient $client,
        private readonly AiSettingsService $settings,
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

        if (! $this->settings->isConfigured()) {
            return $this->fallback($brief, 'ИИ не настроен — создан черновик по вашему описанию. Проверьте и дополните.');
        }

        $prompt = <<<'PROMPT'
Ты помогаешь самозанятому специалисту превратить короткую идею в понятный шаблон задачи.
Верни ТОЛЬКО валидный JSON без markdown и комментариев:
{
  "name": "короткое название шаблона, до 80 символов",
  "summary": "одна строка, что делает шаблон",
  "description": "HTML-описание задачи: что входит, что нужно от клиента, критерии готовности. Только теги p, ul, ol, li, strong, em, h3, a.",
  "checklist": ["3-7 конкретных шагов"],
  "default_priority": "low|medium|high",
  "default_due_days": 3
}
Пиши по-русски, конкретно, без воды и без обещаний сроков от имени владельца.
PROMPT;

        try {
            $response = $this->client->chat([
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => 'Идея шаблона: '.$brief],
            ], [], [
                'temperature' => 0.5,
                'max_tokens' => 900,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = trim((string) ($response['message']['content'] ?? ''));
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $this->normalize($data, $brief, 'ai', null);
        } catch (\Throwable $exception) {
            return $this->fallback($brief, 'ИИ не ответил, поэтому создан черновик. Проверьте формулировки. '.Str::limit($exception->getMessage(), 120));
        }
    }

    /**
     * @return array{name: string, summary: string, description: string, checklist: list<string>, default_priority: string, default_due_days: int|null, source: string, warning: string|null}
     */
    private function fallback(string $brief, string $warning): array
    {
        $firstSentence = trim((string) preg_split('/[.!?\n]/u', $brief)[0]);
        $name = Str::limit($firstSentence !== '' ? $firstSentence : $brief, 80, '');

        $description = '<p>'.e($brief).'</p>'
            .'<p><strong>Что уточнить перед стартом:</strong></p>'
            .'<ul><li>ожидаемый результат и формат;</li><li>что предоставляет клиент;</li><li>критерии готовности.</li></ul>';

        return [
            'name' => $name,
            'summary' => Str::limit($brief, 160),
            'description' => $description,
            'checklist' => [
                'Уточнить ожидания и исходные материалы',
                'Согласовать объём и критерии готовности',
                'Выполнить работу',
                'Передать результат и собрать обратную связь',
            ],
            'default_priority' => 'medium',
            'default_due_days' => 3,
            'source' => 'fallback',
            'warning' => $warning,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, summary: string, description: string, checklist: list<string>, default_priority: string, default_due_days: int|null, source: string, warning: string|null}
     */
    private function normalize(array $data, string $brief, string $source, ?string $warning): array
    {
        $checklist = array_values(array_filter(array_map(
            fn (mixed $item) => trim((string) $item),
            is_array($data['checklist'] ?? null) ? $data['checklist'] : [],
        )));

        if ($checklist === []) {
            $checklist = ['Уточнить детали', 'Согласовать объём', 'Выполнить работу', 'Передать результат'];
        }

        $priority = in_array(($data['default_priority'] ?? 'medium'), ['low', 'medium', 'high'], true)
            ? $data['default_priority']
            : 'medium';

        $dueDays = isset($data['default_due_days']) && is_numeric($data['default_due_days'])
            ? max(1, min(365, (int) $data['default_due_days']))
            : 3;

        return [
            'name' => Str::limit(trim((string) ($data['name'] ?? '')) ?: Str::limit($brief, 80, ''), 80, ''),
            'summary' => Str::limit(trim((string) ($data['summary'] ?? $brief)), 160),
            'description' => $this->sanitizer->sanitize((string) ($data['description'] ?? '<p>'.e($brief).'</p>')),
            'checklist' => array_slice($checklist, 0, 7),
            'default_priority' => $priority,
            'default_due_days' => $dueDays,
            'source' => $source,
            'warning' => $warning,
        ];
    }
}
