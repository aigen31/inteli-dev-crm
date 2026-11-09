<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkChangeTaskStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
