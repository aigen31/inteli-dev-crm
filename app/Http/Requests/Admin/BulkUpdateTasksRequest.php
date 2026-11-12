<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['required', 'uuid', 'distinct', 'exists:tasks,id'],
            'project_id' => ['sometimes', 'nullable', 'uuid', 'exists:projects,id'],
            'client_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'assignee_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:10000'],
            'budget' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fields = array_diff(array_keys($this->rules()), ['task_ids', 'task_ids.*', 'comment']);
            $selected = array_filter($fields, fn (string $field): bool => $this->has($field));

            if ($selected === []) {
                $validator->errors()->add('fields', 'Выберите хотя бы одно поле для изменения.');
            }
        });
    }
}
