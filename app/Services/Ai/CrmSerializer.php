<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\CustomField;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\CustomFieldService;
use App\Services\HtmlSanitizer;

class CrmSerializer
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly CustomFieldService $customFields,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function client(User $client, bool $withDetails = false): array
    {
        $data = [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'is_active' => $client->is_active,
            'custom_fields' => $this->customFields->valuesFor($client),
            'created_at' => $client->created_at?->toIso8601String(),
        ];

        if ($withDetails) {
            $data['tasks'] = $client->clientTasks()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Task $task) => $this->task($task))
                ->all();
            $data['invoices'] = $client->invoices()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Invoice $invoice) => $this->invoice($invoice))
                ->all();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function task(Task $task): array
    {
        $task->loadMissing(['client', 'assignee', 'template']);

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $this->sanitizer->toPlainText($task->description),
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'priority' => $task->priority->value,
            'priority_label' => $task->priority->label(),
            'type' => $task->type->value,
            'source' => $task->source->value,
            'client' => $task->client ? ['id' => $task->client->id, 'name' => $task->client->name] : null,
            'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
            'template' => $task->template ? ['id' => $task->template->id, 'name' => $task->template->name] : null,
            'checklist' => $task->normalizedChecklist(),
            'estimated_minutes' => $task->estimated_minutes,
            'deadline' => $task->deadline?->toDateString(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'custom_fields' => $this->customFields->valuesFor($task),
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['client', 'task']);

        return [
            'id' => $invoice->id,
            'number' => 'INV-'.strtoupper(substr((string) $invoice->id, 0, 8)),
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'description' => $invoice->description,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'payment_method' => $invoice->payment_method->value,
            'payment_method_label' => $invoice->payment_method->label(),
            'is_recurring' => $invoice->is_recurring,
            'client' => $invoice->client ? ['id' => $invoice->client->id, 'name' => $invoice->client->name] : null,
            'task' => $invoice->task ? ['id' => $invoice->task->id, 'title' => $invoice->task->title] : null,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'created_at' => $invoice->created_at?->toIso8601String(),
            'custom_fields' => $this->customFields->valuesFor($invoice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function template(TaskTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'summary' => $template->summary,
            'description' => $this->sanitizer->toPlainText($template->description),
            'checklist' => $template->normalizedChecklist(),
            'default_priority' => $template->default_priority->value,
            'default_type' => $template->default_type->value,
            'default_due_days' => $template->default_due_days,
            'estimated_minutes' => $template->estimated_minutes,
            'is_active' => $template->is_active,
            'usage_count' => $template->usage_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customField(CustomField $field): array
    {
        return $this->customFields->serializeDefinition($field);
    }
}
