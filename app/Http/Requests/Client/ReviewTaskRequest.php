<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([TaskStatus::Completed->value, TaskStatus::RejectedByClient->value])],
            'comment' => ['required_if:status,' . TaskStatus::RejectedByClient->value, 'nullable', 'string', 'max:1000'],
        ];
    }
}
