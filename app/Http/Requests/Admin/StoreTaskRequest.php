<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'task_template_id' => ['nullable', 'uuid', 'exists:task_templates,id'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
            'type' => ['required', Rule::enum(TaskType::class)],
            'status' => ['sometimes', Rule::in([TaskStatus::PendingReview->value, TaskStatus::Queued->value])],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
