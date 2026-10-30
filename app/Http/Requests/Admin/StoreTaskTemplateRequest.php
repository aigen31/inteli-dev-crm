<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
            'default_type' => ['required', Rule::enum(TaskType::class)],
            'default_priority' => ['required', Rule::enum(TaskPriority::class)],
            'default_status' => ['required', Rule::in([TaskStatus::PendingReview->value, TaskStatus::Queued->value])],
            'default_assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'default_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_shared' => ['sometimes', 'boolean'],
        ];
    }
}
