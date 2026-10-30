<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Task\CreateTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskType;
use App\Models\FileAttachment;
use App\Models\FormTemplate;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormTemplateService
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createTask(FormTemplate $template, User $client, array $payload, User $creator): Task
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages(['form' => 'Эта форма сейчас недоступна.']);
        }

        $schema = $template->normalizedSchema();
        $validator = Validator::make($payload, $this->rules($schema, $client), [
            'form_data.required' => 'Заполните форму — без данных заявку не отправить.',
            'title.required' => 'Дайте заявке короткое название.',
            'title.min' => 'Название должно быть чуть подробнее.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $formData = $this->normalize($schema, $data['form_data'] ?? []);

        $task = $this->createTask->execute([
            'client_id' => $client->id,
            'title' => trim((string) ($data['title'] ?? '')) ?: $template->name,
            'description' => $template->description ?: $template->summary,
            'type' => TaskType::OneTime,
            'source' => TaskSource::Client,
            'priority' => $data['priority'] ?? TaskPriority::Medium,
            'deadline' => $data['deadline'] ?? null,
            'form_template_id' => $template->id,
            'form_data' => $formData,
        ], $creator);

        $template->increment('usage_count');

        return $task;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function rules(array $schema, User $client): array
    {
        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'form_data' => ['required', 'array'],
        ];

        foreach ($schema['fields'] as $field) {
            $rules['form_data.fields.'.$field['key']] = $this->fieldRules($field, $client);
        }

        foreach ($schema['groups'] as $group) {
            $min = (int) ($group['min'] ?? 0);
            $max = (int) ($group['max'] ?? 20);
            $groupRules = [$min > 0 ? 'required' : 'nullable', 'array', 'max:'.$max];
            if ($min > 0) {
                $groupRules[] = 'min:'.$min;
            }
            $rules['form_data.groups.'.$group['key']] = $groupRules;
            $rules['form_data.groups.'.$group['key'].'.*'] = ['array'];

            foreach ($group['fields'] ?? [] as $field) {
                $rules['form_data.groups.'.$group['key'].'.*.'.$field['key']] = $this->fieldRules($field, $client);
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    private function fieldRules(array $field, User $client): array
    {
        $required = (bool) ($field['required'] ?? false);
        $rules = [$required ? 'required' : 'nullable'];

        $rules = match ($field['type'] ?? 'text') {
            'textarea' => [...$rules, 'string', 'max:8000'],
            'email' => [...$rules, 'string', 'email', 'max:255'],
            'url' => [...$rules, 'string', 'url', 'max:2048'],
            'number' => [...$rules, 'numeric'],
            'date' => [...$rules, 'date'],
            'checkbox' => $required ? ['accepted', 'boolean'] : ['nullable', 'boolean'],
            'select' => [...$rules, 'string', Rule::in(array_column($field['options'] ?? [], 'value'))],
            'image' => [...$rules, 'uuid', function (string $attribute, mixed $value, \Closure $fail) use ($client): void {
                $file = FileAttachment::query()->find($value);
                if (! $file || $file->uploaded_by !== $client->id) {
                    $fail('Файл не найден. Загрузите изображение ещё раз.');

                    return;
                }
                if (! Str::startsWith($file->mime_type, 'image/')) {
                    $fail('Для этого поля нужно изображение.');
                }
            }],
            default => [...$rules, 'string', 'max:255'],
        };

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $data
     * @return array{fields: array<string, mixed>, groups: array<string, list<array<string, mixed>>>}
     */
    private function normalize(array $schema, array $data): array
    {
        $normalized = ['fields' => [], 'groups' => []];

        foreach ($schema['fields'] as $field) {
            $value = $data['fields'][$field['key']] ?? null;
            $normalized['fields'][$field['key']] = $this->cleanValue($field, $value);
        }

        foreach ($schema['groups'] as $group) {
            $rows = [];
            foreach (($data['groups'][$group['key']] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $clean = [];
                foreach ($group['fields'] ?? [] as $field) {
                    $clean[$field['key']] = $this->cleanValue($field, $row[$field['key']] ?? null);
                }
                if (array_filter($clean, fn ($value) => $value !== null && $value !== '') !== []) {
                    $rows[] = $clean;
                }
            }
            $normalized['groups'][$group['key']] = $rows;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function cleanValue(array $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if ($field['type'] === 'checkbox') {
            return (bool) $value;
        }

        return $value;
    }
}
