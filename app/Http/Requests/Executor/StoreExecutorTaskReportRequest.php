<?php

declare(strict_types=1);

namespace App\Http\Requests\Executor;

use Illuminate\Foundation\Http\FormRequest;

class StoreExecutorTaskReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isExecutor()
            && $this->route('task')?->assignee_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:3', 'max:5000'],
            'title' => ['nullable', 'string', 'max:160'],
            'test_url' => ['nullable', 'url', 'max:500'],
            'screenshot' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
