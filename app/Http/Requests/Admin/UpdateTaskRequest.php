<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'string', 'min:3'],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'deadline' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
