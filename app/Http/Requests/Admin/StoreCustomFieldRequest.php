<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', Rule::enum(CustomFieldEntity::class)],
            'key' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{1,63}$/'],
            'label' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['nullable', 'array', 'max:100'],
            'options.*.value' => ['required_with:options', 'string', 'max:100'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.color' => ['nullable', 'string', 'max:20'],
            'default_value' => ['nullable'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'show_in_list' => ['sometimes', 'boolean'],
            'client_editable' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'placeholder' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = CustomFieldType::tryFrom((string) $this->input('type'));

            if ($type !== null && in_array($type, [CustomFieldType::Select, CustomFieldType::Multiselect], true)) {
                $options = $this->input('options');

                if (! is_array($options) || $options === []) {
                    $validator->errors()->add('options', 'Для поля-выбора добавьте хотя бы один вариант.');
                }
            }
        });
    }
}
