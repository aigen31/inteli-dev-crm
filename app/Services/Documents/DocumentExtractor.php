<?php

declare(strict_types=1);

namespace App\Services\Documents;

use DOMDocument;
use DOMXPath;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Extracts plain text from uploaded reference documents.
 *
 * The text never goes to the model in full: it is stored once and the assistant
 * reads slices through the `read_document` tool. That is the token-economy
 * boundary — extraction is cheap, context is not.
 */
class DocumentExtractor
{
    public const MAX_CHARS = 200_000;

    private const MAX_ARCHIVE_ENTRIES = 500;

    private const MAX_UNCOMPRESSED_BYTES = 40 * 1024 * 1024;

    /**
     * @return array{text: string, chars: int}
     */
    public function extract(string $path, string $name): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $text = match ($extension) {
            'xlsx' => $this->xlsx($path),
            'docx' => $this->docx($path),
            'pptx' => $this->pptx($path),
            'pdf' => $this->pdf($path),
            'csv', 'tsv' => $this->delimited($path, $extension === 'tsv' ? "\t" : ','),
            'txt', 'md', 'markdown', 'json', 'xml', 'html', 'htm', 'yaml', 'yml',
            'log', 'sql', 'ini', 'conf', 'rtf', 'tex' => $this->plain($path),
            default => $this->fallback($path),
        };

        $text = $this->normalize($text);

        if ($text === '') {
            throw new DocumentException('В документе не нашлось текста. Если это скан, пришлите текстовую версию.');
        }

        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        return ['text' => $text, 'chars' => mb_strlen($text)];
    }

    private function xlsx(string $path): string
    {
        $zip = $this->openZip($path);
        $shared = $this->sharedStrings($zip);
        $lines = [];

        for ($sheet = 1; $sheet <= 5; $sheet++) {
            $raw = $zip->getFromName("xl/worksheets/sheet{$sheet}.xml");

            if ($raw === false) {
                break;
            }

            $xpath = $this->xpath($raw);
            $lines[] = "— Лист {$sheet} —";
            $rows = 0;

            foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $cells = [];

                foreach ($xpath->query('./*[local-name()="c"]', $row) as $cell) {
                    $type = $cell->getAttribute('t');
                    $value = '';
                    $node = $xpath->query('./*[local-name()="v"]', $cell)->item(0);

                    if ($node !== null) {
                        $value = $node->textContent;
                    }

                    if ($type === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        foreach ($xpath->query('.//*[local-name()="t"]', $cell) as $text) {
                            $value .= $text->textContent;
                        }
                    }

                    $cells[] = trim($value);
                }

                $line = rtrim(implode("\t", $cells));

                if (trim($line) !== '') {
                    $lines[] = $line;
                    $rows++;
                }

                if ($rows >= 3000 || count($lines) > 15000) {
                    break 2;
                }
            }
        }

        $zip->close();

        return implode("\n", $lines);
    }

    private function docx(string $path): string
    {
        $zip = $this->openZip($path);
        $raw = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($raw === false) {
            throw new DocumentException('DOCX повреждён: нет word/document.xml.');
        }

        $xpath = $this->xpath($raw);
        $lines = [];

        foreach ($xpath->query('//*[local-name()="p"]') as $paragraph) {
            $text = trim($paragraph->textContent);

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return implode("\n", $lines);
    }

    private function pptx(string $path): string
    {
        $zip = $this->openZip($path);
        $lines = [];

        for ($slide = 1; $slide <= 40; $slide++) {
            $raw = $zip->getFromName("ppt/slides/slide{$slide}.xml");

            if ($raw === false) {
                break;
            }

            $xpath = $this->xpath($raw);
            $lines[] = "— Слайд {$slide} —";

            foreach ($xpath->query('//*[local-name()="p"]') as $paragraph) {
                $text = trim($paragraph->textContent);

                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }

        $zip->close();

        return implode("\n", $lines);
    }

    private function pdf(string $path): string
    {
        if (! $this->binaryExists('pdftotext')) {
            throw new DocumentException('PDF пока не поддержан: в контейнере нет pdftotext. Пришлите DOCX или текст.');
        }

        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new DocumentException('Не удалось прочитать PDF: '.mb_substr(trim($process->getErrorOutput()), 0, 200));
        }

        return $process->getOutput();
    }

    private function delimited(string $path, string $delimiter): string
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new DocumentException('Не удалось открыть файл.');
        }

        $lines = [];
        $count = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $count < 6000) {
            $line = trim(implode(' | ', array_map(fn ($cell) => trim((string) $cell), $row)));

            if ($line !== '') {
                $lines[] = $line;
                $count++;
            }
        }

        fclose($handle);

        return implode("\n", $lines);
    }

    private function plain(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new DocumentException('Не удалось открыть файл.');
        }

        if (str_contains($content, "\0")) {
            throw new DocumentException('Файл выглядит бинарным. Пришлите DOCX, XLSX, PDF или текст.');
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1251');

            if (! is_string($converted) || ! mb_check_encoding($converted, 'UTF-8')) {
                throw new DocumentException('Кодировка файла не распознана. Сохраните его в UTF-8.');
            }

            return $converted;
        }

        return strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $content));
    }

    private function fallback(string $path): string
    {
        $content = file_get_contents($path);

        if ($content !== false && ! str_contains($content, "\0") && mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        throw new DocumentException('Этот формат не поддержан. Пришлите DOCX, XLSX, PPTX, PDF, CSV или текст.');
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new DocumentException('Не удалось открыть архив документа. Файл повреждён?');
        }

        $total = 0;

        for ($index = 0; $index < min($zip->numFiles, self::MAX_ARCHIVE_ENTRIES); $index++) {
            $stat = $zip->statIndex($index);
            $total += (int) ($stat['size'] ?? 0);

            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();

                throw new DocumentException('Документ слишком большой после распаковки.');
            }
        }

        return $zip;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');

        if ($raw === false) {
            return [];
        }

        $xpath = $this->xpath($raw);
        $strings = [];

        foreach ($xpath->query('//*[local-name()="si"]') as $item) {
            $text = '';

            foreach ($xpath->query('.//*[local-name()="t"][not(ancestor::*[local-name()="rPh"])]', $item) as $node) {
                $text .= $node->textContent;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function binaryExists(string $binary): bool
    {
        $process = Process::fromShellCommandline('command -v '.escapeshellarg($binary));
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    private function normalize(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
