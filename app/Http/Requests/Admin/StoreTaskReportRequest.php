<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:3', 'max:5000'],
            'recipient_id' => ['nullable', 'uuid', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:160'],
            'test_url' => ['nullable', 'url', 'max:500'],
            'screenshot' => ['nullable', 'image', 'max:10240'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['uuid', 'exists:file_attachments,id'],
        ];
    }
}
