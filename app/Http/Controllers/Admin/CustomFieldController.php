<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomFieldRequest;
use App\Models\CustomField;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    public function store(StoreCustomFieldRequest $request, CustomFieldService $service): RedirectResponse
    {
        $data = $request->validated();
        $entity = CustomFieldEntity::from($data['entity_type']);
        $key = trim((string) ($data['key'] ?? ''));

        if ($key === '') {
            $key = 'field_'.Str::lower(Str::random(6));
        }

        $service->assertKeyAvailable($entity, $key);

        CustomField::create([
            ...$data,
            'key' => $key,
            'entity_type' => $entity,
            'options' => $this->options($data),
            'default_value' => $this->defaultValue($data),
            'sort_order' => $data['sort_order'] ?? ((int) CustomField::query()->forEntity($entity)->max('sort_order') + 1),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'show_in_list' => (bool) ($data['show_in_list'] ?? false),
            'client_editable' => (bool) ($data['client_editable'] ?? false),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'fields'])
            ->with('success', 'Пользовательское поле создано.');
    }

    public function update(
        StoreCustomFieldRequest $request,
        CustomField $customField,
        CustomFieldService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $entity = CustomFieldEntity::from($data['entity_type']);
        $key = trim((string) ($data['key'] ?? ''));

        if ($key === '') {
            $key = $customField->key;
        }

        $service->assertKeyAvailable($entity, $key, $customField->id);

        $customField->update([
            ...$data,
            'key' => $key,
            'entity_type' => $entity,
            'options' => $this->options($data),
            'default_value' => $this->defaultValue($data),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'show_in_list' => (bool) ($data['show_in_list'] ?? false),
            'client_editable' => (bool) ($data['client_editable'] ?? false),
        ]);

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'fields'])
            ->with('success', 'Поле обновлено.');
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        $customField->delete();

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'fields'])
            ->with('success', 'Поле удалено вместе со значениями.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['uuid', 'exists:custom_fields,id'],
        ]);

        foreach ($data['ids'] as $index => $id) {
            CustomField::query()->whereKey($id)->update(['sort_order' => $index + 1]);
        }

        return redirect()
            ->route('admin.settings.edit', ['tab' => 'fields'])
            ->with('success', 'Порядок полей сохранён.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{value: string, label: string, color?: string}>|null
     */
    private function options(array $data): ?array
    {
        $type = CustomFieldType::tryFrom((string) ($data['type'] ?? ''));

        if ($type === null || ! in_array($type, [CustomFieldType::Select, CustomFieldType::Multiselect], true)) {
            return null;
        }

        return array_values(array_map(fn (array $option) => [
            'value' => (string) $option['value'],
            'label' => (string) $option['label'],
            ...(isset($option['color']) && $option['color'] !== '' ? ['color' => (string) $option['color']] : []),
        ], $data['options'] ?? []));
    }

    private function defaultValue(array $data): mixed
    {
        $type = CustomFieldType::tryFrom((string) ($data['type'] ?? ''));
        $value = $data['default_value'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            CustomFieldType::Checkbox => (bool) $value,
            CustomFieldType::Number, CustomFieldType::Currency => (float) $value,
            CustomFieldType::Multiselect => array_values((array) $value),
            default => (string) $value,
        };
    }
}
