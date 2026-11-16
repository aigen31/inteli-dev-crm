<?php

declare(strict_types=1);

namespace App\Services\Briefs;

use App\Models\Brief;
use App\Models\FileAttachment;

/**
 * Превращает ответы брифа в строки для чтения: админка, PDF, Markdown.
 */
class BriefFormatter
{
    /**
     * @return list<array{key: string, title: string, description: string, rows: list<array<string, mixed>>}>
     */
    public function sections(Brief $brief): array
    {
        $answers = $brief->answers();
        $out = [];

        foreach ($brief->sections() as $section) {
            $fields = BriefSchema::visibleFields($section, $answers);
            $rows = [];

            foreach ($fields as $field) {
                $key = (string) $field['key'];
                $value = $answers[$key] ?? null;

                $rows[] = [
                    'key' => $key,
                    'label' => (string) ($field['label'] ?? $key),
                    'type' => (string) $field['type'],
                    'required' => (bool) ($field['required'] ?? false),
                    'value' => $value,
                    'display' => $this->display($field, $value),
                    'answered' => BriefSchema::isAnswered($value),
                ];
            }

            $out[] = [
                'key' => (string) $section['key'],
                'title' => (string) $section['title'],
                'description' => (string) ($section['description'] ?? ''),
                'rows' => $rows,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function display(array $field, mixed $value): string
    {
        if (! BriefSchema::isAnswered($value)) {
            return '—';
        }

        $type = (string) $field['type'];

        return match ($type) {
            'single_choice' => $this->optionLabel($field, (string) $value),
            'multi_choice' => collect((array) $value)
                ->map(fn (mixed $item): string => $this->optionLabel($field, (string) $item))
                ->implode(', '),
            'contact_block' => $this->contactBlock($field, (array) $value),
            'date' => $this->date($value),
            'number', 'scale' => $this->number($field, $value),
            'file' => $this->fileName((string) $value),
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function optionLabel(array $field, string $value): string
    {
        foreach ((array) ($field['options'] ?? []) as $option) {
            if (is_array($option) && (string) ($option['value'] ?? '') === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value === 'other' ? 'Другое' : $value;
    }

    private function date(mixed $value): string
    {
        $timestamp = is_string($value) ? strtotime($value) : false;

        return $timestamp !== false ? date('d.m.Y', $timestamp) : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function number(array $field, mixed $value): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        $formatted = fmod($number, 1.0) === 0.0 ? (string) (int) $number : number_format($number, 2, ',', ' ');
        $unit = trim((string) ($field['validation']['unit'] ?? ''));

        return $unit !== '' ? "{$formatted} {$unit}" : $formatted;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $value
     */
    private function contactBlock(array $field, array $value): string
    {
        $labels = [
            'name' => 'Имя',
            'email' => 'Email',
            'phone' => 'Телефон',
            'telegram' => 'Telegram',
        ];

        foreach ((array) ($field['meta']['fields'] ?? []) as $item) {
            if (is_array($item) && isset($item['key'])) {
                $labels[(string) $item['key']] = (string) ($item['label'] ?? $item['key']);
            } elseif (is_string($item)) {
                $labels[$item] = $labels[$item] ?? $item;
            }
        }

        return collect($value)
            ->map(static fn (mixed $item, string $key): string => ($labels[$key] ?? $key).': '.$item)
            ->implode("\n");
    }

    private function fileName(string $attachmentId): string
    {
        $attachment = FileAttachment::query()->find($attachmentId);

        return $attachment !== null ? $attachment->original_name : 'Файл прикреплён';
    }
}
