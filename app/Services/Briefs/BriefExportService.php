<?php

declare(strict_types=1);

namespace App\Services\Briefs;

use App\Models\Brief;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Экспорт брифа: JSON для интеграций, Markdown для переписки, PDF для клиента.
 */
class BriefExportService
{
    public function __construct(
        private readonly BriefFormatter $formatter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(Brief $brief): array
    {
        return [
            'id' => $brief->id,
            'title' => $brief->title,
            'status' => $brief->status?->value,
            'template' => $brief->template?->title,
            'template_version' => $brief->template_version,
            'client' => $brief->client?->name,
            'project' => $brief->project?->key,
            'progress' => $brief->progress,
            'submitted_at' => $brief->submitted_at?->toIso8601String(),
            'created_at' => $brief->created_at?->toIso8601String(),
            'updated_at' => $brief->updated_at?->toIso8601String(),
            'admin_note' => $brief->admin_note,
            'answers' => $brief->answers(),
            'sections' => $this->formatter->sections($brief),
        ];
    }

    public function toJson(Brief $brief): SymfonyResponse
    {
        return response()->json($this->toArray($brief), 200, [
            'Content-Disposition' => 'attachment; filename="brief-'.substr((string) $brief->id, 0, 8).'.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function toMarkdown(Brief $brief): SymfonyResponse
    {
        $lines = ['# '.$brief->title, ''];

        $meta = array_filter([
            $brief->template?->title !== null ? '**Шаблон:** '.$brief->template->title : null,
            $brief->client?->name !== null ? '**Клиент:** '.$brief->client->name : null,
            $brief->project !== null ? '**Проект:** '.$brief->project->key.' · '.$brief->project->name : null,
            $brief->submitted_at !== null ? '**Отправлен:** '.$brief->submitted_at->format('d.m.Y H:i') : null,
        ]);

        foreach ($meta as $line) {
            $lines[] = $line;
        }

        foreach ($this->formatter->sections($brief) as $section) {
            $lines[] = '';
            $lines[] = '## '.$section['title'];

            if ($section['description'] !== '') {
                $lines[] = '';
                $lines[] = '_'.$section['description'].'_';
            }

            $lines[] = '';

            foreach ($section['rows'] as $row) {
                if (! $row['answered']) {
                    continue;
                }

                $lines[] = '- **'.$row['label'].':** '.str_replace("\n", ' / ', $row['display']);
            }
        }

        if (($brief->admin_note ?? '') !== '') {
            $lines[] = '';
            $lines[] = '## Заметка';
            $lines[] = '';
            $lines[] = $brief->admin_note;
        }

        $body = implode("\n", $lines)."\n";

        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="brief-'.substr((string) $brief->id, 0, 8).'.md"',
        ]);
    }

    public function toPdf(Brief $brief): Response
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml(view('briefs.pdf', [
            'brief' => $brief,
            'sections' => $this->formatter->sections($brief),
            'generatedAt' => now(),
        ])->render(), 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="brief-'.substr((string) $brief->id, 0, 8).'.pdf"',
        ]);
    }
}
