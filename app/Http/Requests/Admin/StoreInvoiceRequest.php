<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Enums\RecurringInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_interval' => ['required_if:is_recurring,true', 'nullable', Rule::enum(RecurringInterval::class)],
            'custom_fields' => ['nullable', 'array'],
            'consent' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->input('payment_method') === PaymentMethod::Transfer->value
                && $this->boolean('is_recurring')
            ) {
                $validator->errors()->add(
                    'is_recurring',
                    'Для прямого перевода рекуррентные платежи недоступны.',
                );
            }
        });
    }
}
