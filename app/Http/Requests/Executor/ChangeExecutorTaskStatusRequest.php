<?php

declare(strict_types=1);

namespace App\Http\Requests\Executor;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeExecutorTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isExecutor();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
