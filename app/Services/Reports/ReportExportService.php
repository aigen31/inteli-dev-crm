<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\TaskReport;
use App\Models\TaskReportItem;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportExportService
{
    public function excel(TaskReport $report): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Отчёт');

        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', $report->title ?: 'Отчёт от '.($report->sent_at ?? $report->created_at)?->format('d.m.Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF1C1823');
        $sheet->getRowDimension(1)->setRowHeight(30);

        $meta = [
            'Задача' => $report->task?->title ? ($report->task->project?->key ? $report->task->project->key.'-'.$report->task->number.' · ' : '').$report->task->title : 'Без задачи',
            'Автор' => $report->author?->name ?: '—',
            'Получатель' => $report->recipient?->name ?: '—',
            'Дата' => ($report->sent_at ?? $report->created_at)?->format('d.m.Y H:i') ?: '—',
        ];
        $row = 3;
        foreach ($meta as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setARGB('FF6B6478');
            $row++;
        }

        $row++;
        $headers = ['№', 'Что проверено', 'Ссылка для тестирования', 'Скриншот'];
        $sheet->fromArray($headers, null, "A{$row}");
        $headerStyle = $sheet->getStyle("A{$row}:D{$row}");
        $headerStyle->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF5B48D6');
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $items = $report->items()->with('screenshot')->get();
        $startRow = $row + 1;
        foreach ($items as $index => $item) {
            $row++;
            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $item->content);
            $sheet->setCellValue("C{$row}", $item->test_url ?: '—');
            $sheet->setCellValue("D{$row}", $item->screenshot ? 'Да' : '—');

            if ($item->test_url) {
                $sheet->getCell("C{$row}")->getHyperlink()->setUrl($item->test_url);
                $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB('FF5B48D6');
                $sheet->getStyle("C{$row}")->getFont()->setUnderline(true);
            }
        }

        $lastRow = max($row, $startRow);
        $tableStyle = $sheet->getStyle("A{$startRow}:D{$lastRow}");
        $tableStyle->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $tableStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE7E1D6');
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(58);
        $sheet->getColumnDimension('C')->setWidth(38);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->freezePane("A{$startRow}");

        $path = tempnam(sys_get_temp_dir(), 'report-xlsx-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $this->filename($report, 'xlsx'))->deleteFileAfterSend(true);
    }

    public function pdf(TaskReport $report): Response
    {
        $report->load(['author:id,name', 'recipient:id,name,email', 'task:id,title,number,project_id', 'task.project:id,key,name', 'items.screenshot']);

        $items = $report->items->map(function (TaskReportItem $item): array {
            return [
                'number' => $item->position + 1,
                'summary' => $item->content,
                'test_url' => $item->test_url,
                'image' => $this->imageData($item),
            ];
        })->all();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml(view('reports.pdf', [
            'report' => $report,
            'items' => $items,
            'title' => $report->title ?: 'Отчёт от '.(($report->sent_at ?? $report->created_at)?->format('d.m.Y') ?? ''),
            'date' => ($report->sent_at ?? $report->created_at)?->format('d.m.Y H:i'),
            'taskReference' => $report->task?->project?->key && $report->task?->number
                ? $report->task->project->key.'-'.$report->task->number
                : null,
        ])->render(), 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($report, 'pdf').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function imageData(TaskReportItem $item): ?string
    {
        $file = $item->screenshot;
        if ($file === null || ! Storage::disk('local')->exists($file->stored_path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($file->stored_path);
        if ($contents === null || strlen($contents) > 10 * 1024 * 1024) {
            return null;
        }

        return 'data:'.$file->mime_type.';base64,'.base64_encode($contents);
    }

    private function filename(TaskReport $report, string $extension): string
    {
        $date = ($report->sent_at ?? $report->created_at)?->format('Y-m-d') ?? now()->format('Y-m-d');
        $title = Str::slug($report->title ?: 'report') ?: 'report';

        return 'report-'.$title.'-'.$date.'.'.$extension;
    }
}
