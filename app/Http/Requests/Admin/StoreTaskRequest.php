<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $projectId = $this->input('project_id') ?: $this->route('task')?->project_id;
            if (! $projectId) {
                return;
            }

            $project = Project::query()->with('members:id,project_id,user_id,delegation')->find($projectId);
            if ($project === null) {
                return;
            }

            $clientId = $this->input('client_id');
            if ($clientId && $clientId !== $project->client_id) {
                $client = User::query()->find($clientId);
                if ($client && ! $client->isAdmin() && ! $project->members->contains(fn ($member) => $member->user_id === $clientId && $member->delegation->value === 'client')) {
                    $validator->errors()->add('client_id', 'Этот пользователь не является клиентом выбранного проекта.');
                }
            }

            $assigneeId = $this->input('assignee_id');
            if ($assigneeId) {
                $assignee = User::query()->find($assigneeId);
                if ($assignee && ! $assignee->isAdmin() && ! $project->members->contains(fn ($member) => $member->user_id === $assigneeId && $member->delegation->value === 'executor')) {
                    $validator->errors()->add('assignee_id', 'Этот пользователь не является исполнителем выбранного проекта.');
                }
            }
        });
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
            'type' => ['required', Rule::enum(TaskType::class)],
            'status' => ['sometimes', Rule::in([TaskStatus::PendingReview->value, TaskStatus::Queued->value])],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'custom_fields' => ['nullable', 'array'],
            'consent' => ['accepted'],
        ];
    }
}
