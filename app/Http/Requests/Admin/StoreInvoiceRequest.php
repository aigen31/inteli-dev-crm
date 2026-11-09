<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Enums\RecurringInterval;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('task_ids') && $this->filled('task_id')) {
            $this->merge(['task_ids' => [$this->input('task_id')]]);
        }
    }

    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1', 'max:50'],
            'task_ids.*' => ['required', 'uuid', 'distinct', 'exists:tasks,id'],
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
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

            $ids = $this->input('task_ids', []);
            if (! is_array($ids) || $ids === []) {
                return;
            }

            $tasks = Task::query()->whereIn('id', $ids)->get(['id', 'client_id', 'status', 'budget', 'title']);

            if ($tasks->contains(fn (Task $task): bool => $task->status === TaskStatus::RejectedByAdmin)) {
                $validator->errors()->add('task_ids', 'Нельзя выставить счёт по отклонённой задаче.');
            }

            if ($tasks->pluck('client_id')->unique()->count() > 1) {
                $validator->errors()->add('task_ids', 'Все задачи в счёте должны принадлежать одному клиенту.');
            }

            if (! $this->filled('amount') && $tasks->contains(fn (Task $task): bool => $task->budget === null)) {
                $validator->errors()->add('amount', 'Укажите сумму: у одной из выбранных задач не заполнен бюджет.');
            }
        });
    }
}
