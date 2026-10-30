<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'template_id' => ['nullable', 'uuid', 'exists:task_templates,id'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
