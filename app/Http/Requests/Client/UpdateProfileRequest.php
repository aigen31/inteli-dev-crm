<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$this->user()->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'messenger_contacts' => ['nullable', 'array'],
        ];
    }
}
