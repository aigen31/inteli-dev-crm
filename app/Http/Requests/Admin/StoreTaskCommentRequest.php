<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['uuid', 'exists:file_attachments,id'],
        ];
    }
}
