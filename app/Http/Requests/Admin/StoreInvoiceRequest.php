<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RecurringInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'uuid', 'exists:tasks,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:500'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_interval' => ['required_if:is_recurring,true', 'nullable', Rule::enum(RecurringInterval::class)],
        ];
    }
}
