<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\ProjectType;
use App\Enums\TaskPriority;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasClientDelegation();
    }

    private function isSelfProject(): bool
    {
        return Project::query()
            ->whereKey($this->input('project_id'))
            ->where('type', ProjectType::Self->value)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'consent' => $this->isSelfProject() ? ['nullable'] : ['accepted'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'starts_at' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
