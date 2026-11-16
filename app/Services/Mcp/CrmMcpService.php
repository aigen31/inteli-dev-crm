<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Actions\Client\CreateClientAction;
use App\Actions\Client\DeactivateClientAction;
use App\Actions\Invoice\CancelInvoiceAction;
use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\MarkInvoiceAsPaidAction;
use App\Actions\Invoice\ProcessRefundAction;
use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\BriefStatus;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\RecurringInterval;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Exceptions\AiException;
use App\Models\Brief;
use App\Models\BriefTemplate;
use App\Models\CustomField;
use App\Models\FormTemplate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\ProjectMember;
use App\Models\ProjectStudy;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Ai\KernelClient;
use App\Services\Briefs\BriefFormatter;
use App\Services\Briefs\BriefService;
use App\Services\ConsentService;
use App\Services\CustomFieldService;
use App\Services\HtmlSanitizer;
use App\Services\ProjectInviteService;
use App\Services\ProjectService;
use App\Services\ReportService;
use App\Services\SettingsService;
use App\Services\TaskTemplateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MCP surface for the CRM. The MCP token is an owner-level machine credential;
 * every call is attributed to the configured administrator.
 */
class CrmMcpService
{
    public function __construct(
        private readonly CreateClientAction $createClient,
        private readonly DeactivateClientAction $deactivateClient,
        private readonly CreateTaskAction $createTask,
        private readonly ChangeTaskStatusAction $changeTaskStatus,
        private readonly CreateInvoiceAction $createInvoice,
        private readonly MarkInvoiceAsPaidAction $markInvoicePaid,
        private readonly CancelInvoiceAction $cancelInvoice,
        private readonly ProcessRefundAction $refundInvoice,
        private readonly ProjectService $projects,
        private readonly ProjectInviteService $invites,
        private readonly ReportService $reports,
        private readonly ConsentService $consents,
        private readonly CustomFieldService $customFields,
        private readonly TaskTemplateService $taskTemplates,
        private readonly SettingsService $settings,
        private readonly HtmlSanitizer $sanitizer,
        private readonly KernelClient $kernel,
        private readonly BriefService $briefs,
        private readonly BriefFormatter $briefFormatter,
    ) {}

    private ?User $actor = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            $this->tool('crm_overview', 'CRM dashboard: clients, projects, active/overdue tasks, invoices and monthly revenue.', [], [], true),
            $this->tool('list_clients', 'Find client accounts by name, email or phone.', [
                'query' => ['type' => 'string'],
                'active' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ], [], true),
            $this->tool('get_client', 'Client card with recent tasks, invoices and custom fields. Use UUID or email.', [
                'client' => ['type' => 'string', 'description' => 'Client UUID or email.'],
            ], ['client'], true),
            $this->tool('list_projects', 'List projects; filter by query, client or archived state.', [
                'query' => ['type' => 'string'],
                'client_id' => ['type' => 'string'],
                'include_archived' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ], [], true),
            $this->tool('get_project', 'Project card with members, invites, tasks and progress. Use UUID or key.', [
                'project' => ['type' => 'string', 'description' => 'Project UUID or key (e.g. KOR).'],
            ], ['project'], true),
            $this->tool('list_tasks', 'List tasks with filters and project references.', [
                'query' => ['type' => 'string'],
                'project_id' => ['type' => 'string'],
                'client_id' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => array_map(fn (TaskStatus $s) => $s->value, TaskStatus::cases())],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ], [], true),
            $this->tool('get_task', 'Task card with comments, checklist, reports and invoices. Use UUID or reference (KEY-12).', [
                'task' => ['type' => 'string'],
            ], ['task'], true),
            $this->tool('list_invoices', 'List invoices with payment status and client.', [
                'status' => ['type' => 'string', 'enum' => array_map(fn (InvoiceStatus $s) => $s->value, InvoiceStatus::cases())],
                'client_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ], [], true),
            $this->tool('get_invoice', 'Invoice with tasks, client, payment method and client payment URL.', [
                'invoice' => ['type' => 'string'],
            ], ['invoice'], true),
            $this->tool('list_reports', 'Recent client reports; optionally filter by recipient or task.', [
                'recipient_id' => ['type' => 'string'],
                'task_id' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ], [], true),
            $this->tool('get_report', 'Full report with description/link groups and screenshot links.', [
                'report' => ['type' => 'string'],
            ], ['report'], true),
            $this->tool('list_users', 'Active CRM users with ids, emails and roles.', [
                'role' => ['type' => 'string', 'enum' => ['admin', 'client']],
            ], [], true),
            $this->tool('list_forms', 'Form templates available to clients, with field schema.', [
                'active_only' => ['type' => 'boolean'],
            ], [], true),
            $this->tool('list_briefs', 'List client briefs with status, progress, client and template. Filter by status, client or project.', [
                'status' => ['type' => 'string', 'enum' => ['open', 'draft', 'submitted', 'archived', 'all']],
                'client_id' => ['type' => 'string'],
                'project_id' => ['type' => 'string'],
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ], [], true),
            $this->tool('get_brief', 'Client brief card: template, client, progress, answers by section and revision history.', [
                'brief' => ['type' => 'string', 'description' => 'Brief UUID.'],
            ], ['brief'], true),
            $this->tool('create_brief', 'Create a client brief from a template and assign it to a client or project. The client fills it in their CRM account.', [
                'template' => ['type' => 'string', 'description' => 'Template UUID or slug (see list_brief_templates).'],
                'client_id' => ['type' => 'string'],
                'project_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ], ['template']),
            $this->tool('update_brief', 'Rename a brief. The title is what the client, the public link and the PDF see.', [
                'brief' => ['type' => 'string', 'description' => 'Brief UUID.'],
                'title' => ['type' => 'string', 'description' => 'New title, up to 200 characters.'],
            ], ['brief', 'title']),
            $this->tool('reopen_brief', 'Send a submitted brief back to the client for revision.', [
                'brief' => ['type' => 'string', 'description' => 'Brief UUID.'],
            ], ['brief']),
            $this->tool('archive_brief', 'Archive a client brief. Destructive: requires confirm=true.', [
                'brief' => ['type' => 'string', 'description' => 'Brief UUID.'],
                'confirm' => ['type' => 'boolean'],
            ], ['brief', 'confirm']),
            $this->tool('list_brief_templates', 'Brief templates available for new briefs: slug, title, section and field counts.', [], [], true),
            $this->tool('list_task_templates', 'Task templates with checklist and defaults.', [
                'client_id' => ['type' => 'string'],
                'active_only' => ['type' => 'boolean'],
            ], [], true),

            $this->tool('update_client', 'Update a client name, email, phone and messenger contacts.', [
                'client' => ['type' => 'string', 'description' => 'Client UUID or email.'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'messenger_contacts' => ['type' => 'object'],
            ], ['client']),
            $this->tool('create_task_template', 'Create a reusable task template.', [
                'name' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'checklist' => ['type' => 'array', 'items' => ['type' => 'object']],
                'default_type' => ['type' => 'string'],
                'default_priority' => ['type' => 'string'],
                'default_status' => ['type' => 'string', 'enum' => ['pending_review', 'queued']],
                'default_assignee_id' => ['type' => 'string'],
                'default_due_days' => ['type' => 'integer'],
                'estimated_minutes' => ['type' => 'integer'],
                'client_id' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
                'is_shared' => ['type' => 'boolean'],
            ], ['name', 'default_type', 'default_priority', 'default_status']),
            $this->tool('apply_task_template', 'Create a task from a task template for a client.', [
                'template' => ['type' => 'string'],
                'client_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'deadline' => ['type' => 'string'],
                'assignee_id' => ['type' => 'string'],
                'project_id' => ['type' => 'string'],
                'budget' => ['type' => 'number'],
            ], ['template', 'client_id']),
            $this->tool('list_custom_fields', 'List user-defined fields for clients, tasks or invoices.', [
                'entity_type' => ['type' => 'string', 'enum' => ['client', 'task', 'invoice']],
            ], [], true),
            $this->tool('get_custom_field_values', 'Read custom field values of one CRM entity.', [
                'entity_type' => ['type' => 'string', 'enum' => ['client', 'task', 'invoice']],
                'entity_id' => ['type' => 'string'],
            ], ['entity_type', 'entity_id'], true),
            $this->tool('set_custom_field_values', 'Set custom field values on one CRM entity.', [
                'entity_type' => ['type' => 'string', 'enum' => ['client', 'task', 'invoice']],
                'entity_id' => ['type' => 'string'],
                'fields' => ['type' => 'object'],
            ], ['entity_type', 'entity_id', 'fields']),
            $this->tool('create_client', 'Create a client account. Requires explicit consent confirmation; returns a temporary password when generated.', [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'password' => ['type' => 'string', 'description' => 'Optional; minimum 8 characters. Generated when omitted.'],
                'consent_confirmed' => ['type' => 'boolean', 'description' => 'Must be true: the client is informed and consent is recorded.'],
            ], ['name', 'email', 'consent_confirmed']),
            $this->tool('create_project', 'Create a project for a client.', [
                'name' => ['type' => 'string'],
                'key' => ['type' => 'string'],
                'client_id' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'site_url' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => array_map(fn (ProjectStatus $s) => $s->value, ProjectStatus::cases())],
                'priority' => ['type' => 'string', 'enum' => array_map(fn (TaskPriority $p) => $p->value, TaskPriority::cases())],
                'starts_at' => ['type' => 'string'],
                'due_at' => ['type' => 'string'],
                'instructions' => ['type' => 'string'],
            ], ['name']),
            $this->tool('create_task', 'Create a task for a client; optionally from a task template.', [
                'client_id' => ['type' => 'string'],
                'project_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'type' => ['type' => 'string', 'enum' => array_map(fn (TaskType $t) => $t->value, TaskType::cases())],
                'status' => ['type' => 'string', 'enum' => ['pending_review', 'queued']],
                'priority' => ['type' => 'string', 'enum' => array_map(fn (TaskPriority $p) => $p->value, TaskPriority::cases())],
                'assignee_id' => ['type' => 'string'],
                'deadline' => ['type' => 'string'],
                'budget' => ['type' => 'number'],
                'estimated_minutes' => ['type' => 'integer'],
                'checklist' => ['type' => 'array', 'items' => ['type' => 'object']],
                'task_template_id' => ['type' => 'string'],
                'custom_fields' => ['type' => 'object'],
            ], ['client_id', 'title', 'description']),
            $this->tool('update_task_status', 'Move a task to an allowed status and optionally add a comment.', [
                'task' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => array_map(fn (TaskStatus $s) => $s->value, TaskStatus::cases())],
                'comment' => ['type' => 'string'],
            ], ['task', 'status']),
            $this->tool('create_invoice', 'Create an invoice for one or more tasks. Money action: requires confirm=true.', [
                'task_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'task_id' => ['type' => 'string'],
                'amount' => ['type' => 'number'],
                'description' => ['type' => 'string'],
                'payment_method' => ['type' => 'string', 'enum' => ['yookassa', 'transfer']],
                'is_recurring' => ['type' => 'boolean'],
                'recurring_interval' => ['type' => 'string', 'enum' => ['monthly', 'quarterly', 'yearly']],
                'confirm' => ['type' => 'boolean'],
            ], ['payment_method', 'confirm']),
            $this->tool('mark_invoice_paid', 'Confirm a manual transfer payment. Money action: requires confirm=true.', [
                'invoice' => ['type' => 'string'],
                'confirm' => ['type' => 'boolean'],
            ], ['invoice', 'confirm']),
            $this->tool('create_project_invite', 'Create a one-time project invite link with a role and expiry.', [
                'project' => ['type' => 'string'],
                'role' => ['type' => 'string', 'enum' => ['manager', 'member', 'viewer']],
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                'email' => ['type' => 'string'],
            ], ['project', 'role']),
            $this->tool('create_report', 'Create a client report in the description+link template. Each group has a description and an optional link, with optional screenshot. Returns the report URL.', [
                'title' => ['type' => 'string'],
                'recipient_id' => ['type' => 'string'],
                'recipient_email' => ['type' => 'string'],
                'task_id' => ['type' => 'string'],
                'template' => ['type' => 'string', 'enum' => ['description_link'], 'description' => 'Format of the report groups.'],
                'items' => [
                    'type' => 'array',
                    'description' => 'Repeatable groups: description + link + optional screenshot.',
                    'items' => [
                        'type' => 'object',
                        'required' => ['description'],
                        'properties' => [
                            'description' => ['type' => 'string'],
                            'link' => ['type' => 'string'],
                            'summary' => ['type' => 'string', 'description' => 'Alias for description.'],
                            'test_url' => ['type' => 'string', 'description' => 'Alias for link.'],
                            'screenshot_base64' => ['type' => 'string'],
                            'screenshot_mime' => ['type' => 'string', 'enum' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif']],
                        ],
                    ],
                ],
            ], ['items']),
            $this->tool('create_task_report', 'Legacy alias of create_report (summary/test_url are accepted).', [
                'title' => ['type' => 'string'],
                'recipient_id' => ['type' => 'string'],
                'recipient_email' => ['type' => 'string'],
                'task_id' => ['type' => 'string'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'description' => ['type' => 'string'],
                            'link' => ['type' => 'string'],
                            'summary' => ['type' => 'string'],
                            'test_url' => ['type' => 'string'],
                            'screenshot_base64' => ['type' => 'string'],
                            'screenshot_mime' => ['type' => 'string'],
                        ],
                    ],
                ],
                'summary' => ['type' => 'string'],
                'test_url' => ['type' => 'string'],
            ], ['items']),
            $this->tool('update_report', 'Edit a client report. Omit a field to keep its current value; pass items to replace the group list. Existing screenshots survive when an item id is kept. This is a write action, not a read.', [
                'report' => ['type' => 'string', 'description' => 'Report UUID.'],
                'title' => ['type' => 'string'],
                'task_id' => ['type' => ['string', 'null'], 'description' => 'Task UUID; null detaches the report from its task.'],
                'recipient_id' => ['type' => 'string'],
                'recipient_email' => ['type' => 'string'],
                'items' => [
                    'type' => 'array',
                    'description' => 'Full replacement list. Omit to keep current groups.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'description' => 'Existing item UUID; omit for a new group.'],
                            'description' => ['type' => 'string'],
                            'summary' => ['type' => 'string', 'description' => 'Alias for description.'],
                            'link' => ['type' => 'string'],
                            'url' => ['type' => 'string', 'description' => 'Alias for link.'],
                            'test_url' => ['type' => 'string', 'description' => 'Alias for link.'],
                            'screenshot_base64' => ['type' => 'string'],
                            'screenshot_mime' => ['type' => 'string', 'enum' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif']],
                            'screenshot_name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ], ['report']),
            $this->tool('delete_report', 'Delete a client report and its groups. Destructive: requires confirm=true.', [
                'report' => ['type' => 'string', 'description' => 'Report UUID.'],
                'confirm' => ['type' => 'boolean', 'description' => 'Must be true.'],
            ], ['report']),
            $this->tool('crm_operation', 'Other CRM writes: client.update/deactivate; project.update/archive/member.*; task.update/checklist/comment; invoice.cancel/refund; task_template.*; form_template.toggle; custom_field.*; settings.get. Set confirm=true for destructive/money operations.', [
                'operation' => ['type' => 'string', 'enum' => [
                    'client.update', 'client.deactivate',
                    'project.update', 'project.archive', 'project.member.add', 'project.member.update', 'project.member.remove',
                    'project.study.start', 'project.study.status', 'project.study.cancel',
                    'project.invite.revoke',
                    'task.update', 'task.checklist', 'task.comment',
                    'invoice.cancel', 'invoice.refund',
                    'task_template.create', 'task_template.update', 'task_template.apply', 'task_template.toggle', 'task_template.delete',
                    'form_template.toggle',
                    'custom_field.list', 'custom_field.create', 'custom_field.update', 'custom_field.delete', 'custom_field.set', 'custom_field.reorder',
                    'settings.get',
                ]],
                'id' => ['type' => 'string', 'description' => 'Target entity UUID or key, depending on operation.'],
                'payload' => ['type' => 'object'],
                'confirm' => ['type' => 'boolean'],
            ], ['operation']),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments, User $actor, Request $request): array
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages(['actor' => 'MCP-инструменты доступны только администратору.']);
        }

        $request->setUserResolver(fn (): User => $actor);
        $this->actor = $actor;

        return match ($name) {
            'crm_overview' => $this->overview(),
            'list_clients' => $this->listClients($arguments),
            'get_client' => $this->getClient($arguments),
            'list_projects' => $this->listProjects($arguments),
            'get_project' => $this->getProject($arguments),
            'list_tasks' => $this->listTasks($arguments),
            'get_task' => $this->getTask($arguments),
            'list_invoices' => $this->listInvoices($arguments),
            'get_invoice' => $this->getInvoice($arguments),
            'list_briefs' => $this->listBriefs($arguments),
            'get_brief' => $this->getBrief($arguments),
            'create_brief' => $this->storeBrief($arguments),
            'update_brief' => $this->updateBrief($arguments),
            'reopen_brief' => $this->reopenBrief($arguments),
            'archive_brief' => $this->archiveBrief($arguments),
            'list_brief_templates' => $this->listBriefTemplates(),
            'list_reports' => $this->listReports($arguments),
            'get_report' => $this->getReport($arguments),
            'list_users' => $this->listUsers($arguments),
            'list_forms' => $this->listForms($arguments),
            'list_task_templates' => $this->listTaskTemplates($arguments),
            'update_client' => $this->updateClientByName($arguments),
            'create_task_template' => $this->storeTaskTemplate($arguments),
            'apply_task_template' => $this->applyTaskTemplateByName($arguments),
            'list_custom_fields' => $this->listCustomFields($arguments),
            'get_custom_field_values' => $this->getCustomFieldValues($arguments),
            'set_custom_field_values' => $this->setCustomFieldValues($arguments),
            'create_client' => $this->storeClient($arguments, $request),
            'create_project' => $this->storeProject($arguments),
            'create_task' => $this->storeTask($arguments, $request),
            'update_task_status' => $this->changeTaskStatus($arguments),
            'create_invoice' => $this->storeInvoice($arguments, $request),
            'mark_invoice_paid' => $this->markPaid($arguments),
            'create_project_invite' => $this->storeInvite($arguments),
            'create_report', 'create_task_report' => $this->storeReport($arguments),
            'update_report' => $this->updateReport($arguments),
            'delete_report' => $this->deleteReport($arguments),
            'crm_operation' => $this->operation($arguments, $request),
            default => throw new \InvalidArgumentException('Unknown tool: '.$name),
        };
    }

    private function actor(): User
    {
        if ($this->actor === null) {
            throw ValidationException::withMessages(['actor' => 'Нет авторизованного администратора.']);
        }

        return $this->actor;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, array $properties, array $required, bool $readOnly = false): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $properties === [] ? (object) [] : $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
            'annotations' => [
                'readOnlyHint' => $readOnly,
                'destructiveHint' => ! $readOnly,
                'idempotentHint' => $readOnly,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function overview(): array
    {
        $now = now();
        $activeStatuses = [TaskStatus::PendingReview, TaskStatus::Queued, TaskStatus::InProgress, TaskStatus::AwaitingReview, TaskStatus::RejectedByClient];

        return [
            'clients' => User::query()->where('role', UserRole::Client)->where('is_active', true)->count(),
            'projects' => Project::query()->whereNull('archived_at')->count(),
            'tasks' => [
                'active' => Task::query()->whereIn('status', $activeStatuses)->count(),
                'awaiting_review' => Task::query()->where('status', TaskStatus::AwaitingReview)->count(),
                'overdue' => Task::query()->whereNotNull('deadline')->whereDate('deadline', '<', $now)->whereNotIn('status', [TaskStatus::Completed, TaskStatus::RejectedByAdmin])->count(),
            ],
            'invoices' => [
                'pending' => Invoice::query()->where('status', InvoiceStatus::Pending)->count(),
                'pending_amount' => (float) Invoice::query()->where('status', InvoiceStatus::Pending)->sum('amount'),
                'paid_this_month' => (float) Invoice::query()->where('status', InvoiceStatus::Paid)->whereBetween('paid_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])->sum('amount'),
            ],
            'recent_tasks' => Task::query()->with('project:id,key,name')->latest()->limit(5)->get()->map(fn (Task $task): array => $this->taskSummary($task))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listClients(array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 100);
        $query = User::query()->where('role', UserRole::Client)->withCount(['clientTasks', 'invoices'])->latest();

        if (array_key_exists('active', $arguments)) {
            $query->where('is_active', (bool) $arguments['active']);
        }

        if (filled($arguments['query'] ?? null)) {
            $search = '%'.(string) $arguments['query'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search));
        }

        return ['clients' => $query->limit($limit)->get()->map(fn (User $client): array => $this->clientSummary($client))->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getClient(array $arguments): array
    {
        $client = $this->findClient((string) ($arguments['client'] ?? ''));
        $client->load([
            'clientTasks' => fn ($q) => $q->with(['project:id,key,name', 'assignee:id,name'])->latest()->limit(10),
            'invoices' => fn ($q) => $q->latest()->limit(10),
            'customFieldValues.customField',
        ]);

        return [
            ...$this->clientSummary($client),
            'messenger_contacts' => $client->messenger_contacts,
            'tasks' => $client->clientTasks->map(fn (Task $task): array => $this->taskSummary($task))->all(),
            'invoices' => $client->invoices->map(fn (Invoice $invoice): array => $this->invoiceSummary($invoice))->all(),
            'custom_fields' => $this->customFields->serializedValues($client),
            'url' => route('admin.clients.show', $client),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listProjects(array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 100);
        $query = Project::query()->with('client:id,name')->withCount(['tasks', 'members'])->latest();

        if (! ($arguments['include_archived'] ?? false)) {
            $query->whereNull('archived_at');
        }

        if (filled($arguments['client_id'] ?? null)) {
            $query->where('client_id', $arguments['client_id']);
        }

        if (filled($arguments['query'] ?? null)) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.(string) $arguments['query'].'%')->orWhere('key', 'like', '%'.(string) $arguments['query'].'%'));
        }

        return ['projects' => $query->limit($limit)->get()->map(fn (Project $project): array => $this->projectSummary($project))->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getProject(array $arguments): array
    {
        $project = $this->findProject((string) ($arguments['project'] ?? ''));
        $project->load(['client:id,name,email', 'members.user:id,name,email', 'invites.creator:id,name', 'invites.acceptedUser:id,name']);

        return [
            ...$this->projectSummary($project),
            'members' => $project->members->map(fn (ProjectMember $member): array => [
                'id' => $member->id,
                'user' => $member->user?->only(['id', 'name', 'email']),
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
            ])->all(),
            'invites' => $project->invites->map(fn (ProjectInvite $invite): array => [
                'id' => $invite->id,
                'role' => $invite->role->value,
                'email' => $invite->email,
                'status' => $invite->status(),
                'expires_at' => $invite->expires_at->toIso8601String(),
                'used_at' => $invite->used_at?->toIso8601String(),
            ])->all(),
            'tasks' => $project->tasks()->with('assignee:id,name')->latest()->limit(20)->get()->map(fn (Task $task): array => $this->taskSummary($task))->all(),
            'progress' => $this->projects->progress($project),
            'url' => route('admin.projects.show', $project),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listTasks(array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 100);
        $query = Task::query()->with(['client:id,name', 'project:id,key,name', 'assignee:id,name'])->latest();

        foreach (['project_id', 'client_id', 'status'] as $filter) {
            if (filled($arguments[$filter] ?? null)) {
                $query->where($filter, $arguments[$filter]);
            }
        }

        if (filled($arguments['query'] ?? null)) {
            $query->where('title', 'like', '%'.(string) $arguments['query'].'%');
        }

        return ['tasks' => $query->limit($limit)->get()->map(fn (Task $task): array => $this->taskSummary($task))->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getTask(array $arguments): array
    {
        $task = $this->findTask((string) ($arguments['task'] ?? ''));
        $task->load(['client:id,name,email', 'project:id,key,name', 'assignee:id,name', 'comments.author:id,name', 'reports.recipient:id,name', 'reports.items', 'invoices']);

        return [
            ...$this->taskSummary($task),
            'description' => $task->description,
            'checklist' => $task->checklist ?? [],
            'comments' => $task->comments->map(fn ($comment): array => [
                'id' => $comment->id,
                'author' => $comment->author?->name,
                'content' => $comment->content,
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->all(),
            'reports' => $task->reports->map(fn (TaskReport $report): array => $this->reportSummary($report))->all(),
            'invoices' => $task->invoices->map(fn (Invoice $invoice): array => $this->invoiceSummary($invoice))->all(),
            'url' => route('admin.tasks.show', $task),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listInvoices(array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 100);
        $query = Invoice::query()->with(['client:id,name', 'task:id,title'])->latest();

        if (filled($arguments['status'] ?? null)) {
            $query->where('status', $arguments['status']);
        }
        if (filled($arguments['client_id'] ?? null)) {
            $query->where('client_id', $arguments['client_id']);
        }

        return ['invoices' => $query->limit($limit)->get()->map(fn (Invoice $invoice): array => $this->invoiceSummary($invoice))->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getInvoice(array $arguments): array
    {
        $invoice = Invoice::query()->with(['client:id,name,email', 'task:id,title', 'tasks:id,title'])->findOrFail((string) ($arguments['invoice'] ?? ''));

        return [
            ...$this->invoiceSummary($invoice),
            'client' => $invoice->client?->only(['id', 'name', 'email']),
            'tasks' => $invoice->tasks->map(fn (Task $task): array => ['id' => $task->id, 'title' => $task->title, 'amount' => $task->pivot?->amount])->all(),
            'client_url' => route('client.invoices.show', $invoice),
            'admin_url' => route('admin.invoices.show', $invoice),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listBriefs(array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 100);
        $status = (string) ($arguments['status'] ?? 'open');

        $query = Brief::query()->with(['template:id,title,slug', 'client:id,name', 'project:id,key,name']);

        match ($status) {
            'draft' => $query->whereIn('status', [BriefStatus::Draft, BriefStatus::InProgress]),
            'submitted' => $query->where('status', BriefStatus::Submitted),
            'archived' => $query->where('status', BriefStatus::Archived),
            'all' => null,
            default => $query->whereIn('status', BriefStatus::openCases()),
        };

        if (filled($arguments['client_id'] ?? null)) {
            if (! Str::isUuid((string) $arguments['client_id'])) {
                throw ValidationException::withMessages(['client_id' => 'client_id must be a UUID.']);
            }

            $query->where('client_id', $arguments['client_id']);
        }

        if (filled($arguments['project_id'] ?? null)) {
            $query->where('project_id', $arguments['project_id']);
        }

        if (filled($arguments['query'] ?? null)) {
            $query->where('title', 'like', '%'.(string) $arguments['query'].'%');
        }

        return ['briefs' => $query->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn (Brief $brief): array => $this->briefSummary($brief))->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getBrief(array $arguments): array
    {
        $brief = $this->findBrief((string) ($arguments['brief'] ?? ''));
        $brief->load(['template:id,title,slug,schema', 'client:id,name,email', 'project:id,key,name', 'creator:id,name', 'revisions.creator:id,name']);

        return [
            ...$this->briefSummary($brief),
            'submitted_at' => $brief->submitted_at?->toIso8601String(),
            'created_at' => $brief->created_at?->toIso8601String(),
            'admin_note' => $brief->admin_note,
            'url' => route('admin.briefs.show', $brief),
            'sections' => $this->briefFormatter->sections($brief),
            'revisions' => $brief->revisions->map(fn ($revision): array => [
                'version' => $revision->version,
                'reason' => $revision->reason,
                'author' => $revision->creator?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function storeBrief(array $arguments): array
    {
        $template = BriefTemplate::query()
            ->where(function ($query) use ($arguments): void {
                $query->where('slug', (string) $arguments['template']);

                if (Str::isUuid((string) $arguments['template'])) {
                    $query->orWhere('id', (string) $arguments['template']);
                }
            })
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages(['template' => 'Шаблон брифа не найден или выключен.']);
        }

        $brief = $this->briefs->create($template, [
            'title' => $arguments['title'] ?? null,
            'client_id' => $arguments['client_id'] ?? null,
            'project_id' => $arguments['project_id'] ?? null,
        ], $this->actor());

        return $this->briefSummary($brief->load(['template:id,title,slug', 'client:id,name', 'project:id,key,name']));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reopenBrief(array $arguments): array
    {
        $brief = $this->findBrief((string) ($arguments['brief'] ?? ''));

        if ($brief->status !== BriefStatus::Submitted) {
            throw ValidationException::withMessages(['brief' => 'На доработку можно вернуть только отправленный бриф.']);
        }

        return $this->briefSummary($this->briefs->reopen($brief, $this->actor()));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateBrief(array $arguments): array
    {
        $brief = $this->findBrief((string) ($arguments['brief'] ?? ''));
        $title = trim((string) ($arguments['title'] ?? ''));

        return $this->briefSummary($this->briefs->rename($brief, $title));
    }

    private function archiveBrief(array $arguments): array
    {
        if (! ($arguments['confirm'] ?? false)) {
            throw ValidationException::withMessages(['confirm' => 'Архивация требует confirm=true.']);
        }

        $brief = $this->findBrief((string) ($arguments['brief'] ?? ''));

        return $this->briefSummary($this->briefs->archive($brief));
    }

    /**
     * @return array<string, mixed>
     */
    private function listBriefTemplates(): array
    {
        return ['templates' => BriefTemplate::query()->active()->orderBy('sort_order')->orderBy('title')->get()
            ->map(fn (BriefTemplate $template): array => $template->toLibraryArray())->all()];
    }

    /**
     * @return array<string, mixed>
     */
    private function briefSummary(Brief $brief): array
    {
        return [
            'id' => $brief->id,
            'title' => $brief->title,
            'status' => $brief->status?->value,
            'status_label' => $brief->status?->label(),
            'progress' => $brief->progress,
            'template' => $brief->template?->only(['id', 'title', 'slug']),
            'client' => $brief->client?->only(['id', 'name']),
            'project' => $brief->project ? ['id' => $brief->project->id, 'key' => $brief->project->key, 'name' => $brief->project->name] : null,
            'updated_at' => $brief->updated_at?->toIso8601String(),
        ];
    }

    private function findBrief(string $value): Brief
    {
        $brief = Str::isUuid($value) ? Brief::query()->find($value) : null;

        if ($brief === null) {
            throw ValidationException::withMessages(['brief' => 'Бриф не найден.']);
        }

        return $brief;
    }

    private function listReports(array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 100);
        $query = TaskReport::query()->with(['recipient:id,name,email', 'task:id,title'])->latest();

        if (filled($arguments['recipient_id'] ?? null)) {
            $query->where('recipient_id', $arguments['recipient_id']);
        }
        if (filled($arguments['task_id'] ?? null)) {
            $query->where('task_id', $arguments['task_id']);
        }

        return ['reports' => $query->limit($limit)->get()->map(fn (TaskReport $report): array => $this->reportSummary($report))->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getReport(array $arguments): array
    {
        $report = TaskReport::query()->with(['author:id,name', 'recipient:id,name,email', 'task:id,title', 'items.screenshot'])->findOrFail((string) ($arguments['report'] ?? ''));

        return $this->reportDetails($report);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateReport(array $arguments): array
    {
        $report = TaskReport::query()
            ->with(['author:id,name', 'recipient:id,name,email', 'task:id,title', 'items.screenshot'])
            ->findOrFail((string) ($arguments['report'] ?? ''));

        $items = array_key_exists('items', $arguments)
            ? $this->normalizeReportItems(is_array($arguments['items']) ? $arguments['items'] : [])
            : $report->items->map(fn ($item): array => [
                'id' => $item->id,
                'summary' => $item->content,
                'test_url' => $item->test_url,
            ])->all();

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Передайте хотя бы одну группу отчёта.']);
        }

        $updated = $this->reports->update($report, request()->user(), [
            'task_id' => array_key_exists('task_id', $arguments)
                ? (filled($arguments['task_id']) ? $arguments['task_id'] : null)
                : $report->task_id,
            'title' => array_key_exists('title', $arguments) ? $arguments['title'] : $report->title,
            'recipient_id' => $this->reportRecipientId($report, $arguments),
            'items' => $items,
        ]);

        return [
            ...$this->reportDetails($updated),
            'updated' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteReport(array $arguments): array
    {
        if (($arguments['confirm'] ?? false) !== true) {
            throw ValidationException::withMessages(['confirm' => 'Удаление отчёта требует confirm=true.']);
        }

        $report = TaskReport::query()->findOrFail((string) ($arguments['report'] ?? ''));
        $payload = [
            'id' => $report->id,
            'title' => $report->title,
            'display_title' => $report->title ?: 'Отчёт от '.$report->sent_at?->format('d.m.Y'),
        ];

        $this->reports->delete($report);

        return [
            ...$payload,
            'deleted' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function reportRecipientId(TaskReport $report, array $arguments): string
    {
        if (filled($arguments['recipient_id'] ?? null)) {
            return (string) $arguments['recipient_id'];
        }

        if (filled($arguments['recipient_email'] ?? null)) {
            $id = User::query()
                ->where('is_active', true)
                ->where('email', (string) $arguments['recipient_email'])
                ->value('id');

            if ($id === null) {
                throw ValidationException::withMessages(['recipient_email' => 'Активный получатель с таким email не найден.']);
            }

            return (string) $id;
        }

        if (filled($report->recipient_id)) {
            return (string) $report->recipient_id;
        }

        throw ValidationException::withMessages(['recipient_id' => 'Укажите получателя отчёта.']);
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeReportItems(array $items): array
    {
        return array_values(array_map(fn (mixed $item): array => [
            'id' => is_array($item) && filled($item['id'] ?? null) ? $item['id'] : null,
            'summary' => is_array($item) ? ($item['description'] ?? $item['summary'] ?? '') : '',
            'test_url' => is_array($item) ? ($item['link'] ?? $item['url'] ?? $item['test_url'] ?? null) : null,
            'screenshot_base64' => is_array($item) ? ($item['screenshot_base64'] ?? null) : null,
            'screenshot_mime' => is_array($item) ? ($item['screenshot_mime'] ?? null) : null,
            'screenshot_name' => is_array($item) ? ($item['screenshot_name'] ?? null) : null,
        ], $items));
    }

    /**
     * @return array<string, mixed>
     */
    private function reportDetails(TaskReport $report): array
    {
        return [
            ...$this->reportSummary($report),
            'author' => $report->author?->only(['id', 'name']),
            'recipient' => $report->recipient?->only(['id', 'name', 'email']),
            'items' => $report->items->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->content,
                'link' => $item->test_url,
                'screenshot_url' => $item->screenshot_id ? route('reports.screenshot', ['report' => $report, 'item' => $item->id]) : null,
            ])->all(),
            'url' => route('reports.show', $report),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listUsers(array $arguments): array
    {
        $query = User::query()->where('is_active', true)->orderBy('name')->limit(100);

        if (filled($arguments['role'] ?? null)) {
            $query->where('role', $arguments['role']);
        }

        return ['users' => $query->get(['id', 'name', 'email', 'role'])->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listForms(array $arguments): array
    {
        $query = FormTemplate::query()->orderBy('sort_order')->orderBy('name');
        if ($arguments['active_only'] ?? true) {
            $query->where('is_active', true);
        }

        return ['forms' => $query->get()->map(fn (FormTemplate $form): array => [
            'id' => $form->id,
            'key' => $form->key,
            'name' => $form->name,
            'description' => $form->description,
            'is_active' => $form->is_active,
            ...$form->toLibraryArray(),
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listTaskTemplates(array $arguments): array
    {
        $query = TaskTemplate::query()->with(['client:id,name', 'defaultAssignee:id,name'])->orderBy('name');
        if ($arguments['active_only'] ?? true) {
            $query->where('is_active', true);
        }
        if (filled($arguments['client_id'] ?? null)) {
            $query->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', $arguments['client_id']));
        }

        return ['templates' => $query->limit(100)->get()->map(fn (TaskTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
            'summary' => $template->summary,
            'client_id' => $template->client_id,
            'client' => $template->client?->name,
            'default_type' => $template->default_type?->value,
            'default_priority' => $template->default_priority?->value,
            'default_status' => $template->default_status?->value,
            'checklist' => $template->normalizedChecklist(),
            'is_active' => $template->is_active,
            'usage_count' => $template->usage_count,
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function storeClient(array $arguments, Request $request): array
    {
        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
            'consent_confirmed' => ['accepted'],
        ])->validate();

        $generated = empty($data['password']);
        $password = $generated ? Str::password(12) : $data['password'];

        $client = $this->createClient->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $password,
        ]);

        $this->consents->record($client, 'mcp-client-create', $request, [
            'confirmed_by' => $request->user()?->id,
            'basis' => 'contract',
            'source' => 'mcp',
        ], 'privacy');

        return [
            ...$this->clientSummary($client->refresh()),
            'temporary_password' => $generated ? $password : null,
            'note' => 'Пароль показывается один раз. Передайте его клиенту безопасным способом.',
            'url' => route('admin.clients.show', $client),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function storeProject(array $arguments): array
    {
        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'key' => ['nullable', 'string', 'max:12'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:20000'],
            'site_url' => ['nullable', 'url', 'max:500'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:20000'],
        ])->validate();

        $project = $this->projects->create(request()->user(), $data);

        return [...$this->projectSummary($project), 'url' => route('admin.projects.show', $project)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function storeTask(array $arguments, Request $request): array
    {
        $data = Validator::make($arguments, [
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:3'],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'status' => ['sometimes', 'in:pending_review,queued'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'deadline' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'task_template_id' => ['nullable', 'uuid', 'exists:task_templates,id'],
            'custom_fields' => ['nullable', 'array'],
        ])->validate();

        $client = $this->findClient($data['client_id']);
        if (! $client->isClient()) {
            throw ValidationException::withMessages(['client_id' => 'Пользователь не является клиентом.']);
        }

        if (filled($data['project_id'] ?? null)) {
            $this->findProject($data['project_id']);
        }

        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields'], $data['task_template_id']);

        if (filled($arguments['task_template_id'] ?? null)) {
            $template = TaskTemplate::query()->findOrFail($arguments['task_template_id']);
            $task = $this->taskTemplates->apply($template, $client, $request->user(), [
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'deadline' => $data['deadline'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'budget' => $data['budget'] ?? null,
            ]);
        } else {
            $task = $this->createTask->execute($data, $request->user());
        }

        $this->customFields->sync($task, $customValues, $request->user());

        return [...$this->taskSummary($task->refresh()), 'url' => route('admin.tasks.show', $task)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function changeTaskStatus(array $arguments): array
    {
        $data = Validator::make($arguments, [
            'task' => ['required', 'string'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $task = $this->findTask($data['task']);
        $status = TaskStatus::from($data['status']);
        $this->changeTaskStatus->execute($task, $status, request()->user(), $data['comment'] ?? null);

        return [...$this->taskSummary($task->refresh()), 'url' => route('admin.tasks.show', $task)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function storeInvoice(array $arguments, Request $request): array
    {
        $this->confirm($arguments, 'Создание счёта');

        if (empty($arguments['task_ids']) && filled($arguments['task_id'] ?? null)) {
            $arguments['task_ids'] = [$arguments['task_id']];
        }

        $data = Validator::make($arguments, [
            'task_ids' => ['required', 'array', 'min:1', 'max:50'],
            'task_ids.*' => ['required', 'uuid', 'distinct', 'exists:tasks,id'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurring_interval' => ['required_if:is_recurring,true', 'nullable', Rule::enum(RecurringInterval::class)],
            'custom_fields' => ['nullable', 'array'],
        ])->validate();

        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        $invoice = $this->createInvoice->execute($data);
        $this->customFields->sync($invoice, $customValues, $request->user());
        $this->consents->record($invoice->client, 'mcp-invoice-create', $request, [
            'confirmed_by' => $request->user()?->id,
            'invoice_id' => $invoice->id,
            'basis' => 'contract',
        ], 'privacy');

        return [...$this->invoiceSummary($invoice->refresh()), 'admin_url' => route('admin.invoices.show', $invoice), 'client_url' => route('client.invoices.show', $invoice)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function markPaid(array $arguments): array
    {
        $this->confirm($arguments, 'Подтверждение оплаты');
        $invoice = Invoice::query()->findOrFail((string) ($arguments['invoice'] ?? ''));
        $this->markInvoicePaid->execute($invoice);

        return $this->invoiceSummary($invoice->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function storeInvite(array $arguments): array
    {
        $data = Validator::make($arguments, [
            'project' => ['required', 'string'],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
            'days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ])->validate();

        $project = $this->findProject($data['project']);
        $result = $this->invites->create($project, request()->user(), [
            'role' => $data['role'],
            'days' => $data['days'] ?? 7,
            'email' => $data['email'] ?? null,
        ]);

        return [
            'id' => $result['invite']->id,
            'url' => $result['url'],
            'role' => $result['invite']->role->value,
            'email' => $result['invite']->email,
            'expires_at' => $result['invite']->expires_at->toIso8601String(),
            'note' => 'Ссылка одноразовая; показывается только сейчас.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function storeReport(array $arguments): array
    {
        $items = is_array($arguments['items'] ?? null) ? $arguments['items'] : [];

        if ($items === [] && filled($arguments['summary'] ?? null)) {
            $items = [[
                'summary' => $arguments['summary'],
                'test_url' => $arguments['test_url'] ?? null,
            ]];
        }

        $normalized = array_map(fn (array $item): array => [
            'summary' => $item['description'] ?? $item['summary'] ?? '',
            'test_url' => $item['link'] ?? $item['url'] ?? $item['test_url'] ?? null,
            'screenshot_base64' => $item['screenshot_base64'] ?? null,
            'screenshot_mime' => $item['screenshot_mime'] ?? null,
            'screenshot_name' => $item['screenshot_name'] ?? null,
        ], $items);

        $report = $this->reports->create(request()->user(), [
            'task_id' => $arguments['task_id'] ?? null,
            'title' => $arguments['title'] ?? null,
            'recipient_id' => $arguments['recipient_id'] ?? null,
            'recipient_email' => $arguments['recipient_email'] ?? null,
            'items' => $normalized,
        ]);

        return [
            'id' => $report->id,
            'title' => $report->title,
            'display_title' => $report->title ?: 'Отчёт от '.$report->sent_at?->format('d.m.Y'),
            'template' => $arguments['template'] ?? 'description_link',
            'recipient' => $report->recipient?->only(['id', 'name', 'email']),
            'task' => $report->task?->only(['id', 'title']),
            'items' => $report->items->map(fn ($item): array => [
                'description' => $item->content,
                'link' => $item->test_url,
                'screenshot' => $item->screenshot_id !== null,
            ])->all(),
            'url' => route('reports.show', $report),
            'sent_at' => $report->sent_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function operation(array $arguments, Request $request): array
    {
        $operation = (string) ($arguments['operation'] ?? '');
        $payload = is_array($arguments['payload'] ?? null) ? $arguments['payload'] : [];
        $id = (string) ($arguments['id'] ?? $payload['id'] ?? '');

        return match ($operation) {
            'client.update' => $this->updateClient($id, $payload),
            'client.deactivate' => $this->deactivateClient($id, $arguments),
            'project.update' => $this->updateProject($id, $payload),
            'project.archive' => $this->archiveProject($id, $arguments),
            'project.member.add' => $this->addMember($id, $payload),
            'project.member.update' => $this->updateMember($id, $payload),
            'project.member.remove' => $this->removeMember($id, $payload),
            'project.study.start' => $this->startStudy($id, $payload),
            'project.study.status' => $this->studyStatus($id, $payload),
            'project.study.cancel' => $this->cancelStudy($id, $payload),
            'project.invite.revoke' => $this->revokeInvite($id, $payload),
            'task.update' => $this->updateTask($id, $payload),
            'task.checklist' => $this->updateChecklist($id, $payload),
            'task.comment' => $this->addComment($id, $payload, $request),
            'invoice.cancel' => $this->cancelInvoice($id, $arguments),
            'invoice.refund' => $this->refundInvoice($id, $arguments),
            'task_template.create' => $this->storeTaskTemplate($payload),
            'task_template.update' => $this->updateTaskTemplate($id, $payload),
            'task_template.apply' => $this->applyTaskTemplate($id, $payload),
            'task_template.toggle' => $this->toggleTaskTemplate($id),
            'task_template.delete' => $this->deleteTaskTemplate($id, $arguments),
            'form_template.toggle' => $this->toggleFormTemplate($id),
            'custom_field.list' => $this->listCustomFields($payload),
            'custom_field.create' => $this->storeCustomField($payload),
            'custom_field.update' => $this->updateCustomField($id, $payload),
            'custom_field.delete' => $this->deleteCustomField($id, $arguments),
            'custom_field.set' => $this->setCustomField($id, $payload),
            'custom_field.reorder' => $this->reorderCustomFields($payload),
            'settings.get' => $this->getSettings(),
            default => throw new \InvalidArgumentException('Unknown CRM operation: '.$operation),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateClientByName(array $arguments): array
    {
        $client = $this->findClient((string) ($arguments['client'] ?? ''));
        unset($arguments['client']);

        return $this->updateClient($client->id, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function applyTaskTemplateByName(array $arguments): array
    {
        $template = TaskTemplate::query()->findOrFail((string) ($arguments['template'] ?? ''));
        unset($arguments['template']);

        return $this->applyTaskTemplate($template->id, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getCustomFieldValues(array $arguments): array
    {
        $entity = CustomFieldEntity::from((string) $arguments['entity_type']);
        $model = $entity->modelClass()::query()->findOrFail((string) $arguments['entity_id']);

        return [
            'entity_type' => $entity->value,
            'entity_id' => $model->getKey(),
            'fields' => $this->customFields->serializedValues($model),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setCustomFieldValues(array $arguments): array
    {
        $entity = CustomFieldEntity::from((string) $arguments['entity_type']);
        $model = $entity->modelClass()::query()->findOrFail((string) $arguments['entity_id']);
        $fields = is_array($arguments['fields'] ?? null) ? $arguments['fields'] : [];
        $this->customFields->sync($model, $fields, request()->user());

        return [
            'entity_type' => $entity->value,
            'entity_id' => $model->getKey(),
            'fields' => $this->customFields->serializedValues($model->refresh()),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateClient(string $id, array $payload): array
    {
        $client = $this->findClient($id);
        $data = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$client->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'messenger_contacts' => ['nullable', 'array'],
        ])->validate();

        $client->update($data);

        return [...$this->clientSummary($client->refresh()), 'url' => route('admin.clients.show', $client)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deactivateClient(string $id, array $arguments): array
    {
        $this->confirm($arguments, 'Деактивация клиента');
        $client = $this->findClient($id);
        $this->deactivateClient->execute($client);

        return [...$this->clientSummary($client->refresh()), 'deactivated' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateProject(string $id, array $payload): array
    {
        $project = $this->findProject($id);
        $data = Validator::make($payload, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'key' => ['nullable', 'string', 'max:12'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:20000'],
            'site_url' => ['nullable', 'url', 'max:500'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'agents_md' => ['nullable', 'string'],
        ])->validate();

        $this->projects->update($project, $data);

        return [...$this->projectSummary($project->refresh()), 'url' => route('admin.projects.show', $project)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function archiveProject(string $id, array $arguments): array
    {
        $this->confirm($arguments, 'Архивация проекта');
        $project = $this->findProject($id);
        $this->projects->archive($project);

        return [...$this->projectSummary($project->refresh()), 'archived' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function addMember(string $projectId, array $payload): array
    {
        $data = Validator::make($payload, [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ])->validate();

        $project = $this->findProject($projectId);
        $member = $project->members()->updateOrCreate(
            ['user_id' => $data['user_id']],
            ['role' => $data['role'], 'added_by' => request()->user()?->id],
        );

        return ['member' => $this->memberPayload($member->load('user:id,name,email'))];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateMember(string $projectId, array $payload): array
    {
        $project = $this->findProject($projectId);
        $member = ProjectMember::query()->where('project_id', $project->id)->findOrFail((string) ($payload['member_id'] ?? ''));
        $role = Validator::make($payload, ['role' => ['required', Rule::enum(ProjectMemberRole::class)]])->validate()['role'];
        $member->update(['role' => $role]);

        return ['member' => $this->memberPayload($member->load('user:id,name,email'))];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function startStudy(string $projectId, array $payload): array
    {
        $project = $this->findProject($projectId);

        if (blank($project->site_url)) {
            throw ValidationException::withMessages(['project' => 'У проекта не указан сайт для изучения.']);
        }

        $study = $project->studies()->create([
            'actor_id' => request()->user()?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $this->kernel->startStudy(request()->user(), [
                'url' => $project->site_url,
                'focus' => $payload['focus'] ?? $project->name,
                'agents_md' => $project->agents_md,
                'instructions' => $project->instructions,
            ]);
        } catch (AiException $exception) {
            $study->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            throw ValidationException::withMessages(['study' => 'Не удалось запустить изучение: '.$exception->getMessage()]);
        }

        $study->update([
            'kernel_study_id' => $result['id'] ?? null,
            'events' => $result['events'] ?? [],
        ]);

        return ['study' => $this->studyPayload($study->refresh())];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function studyStatus(string $projectId, array $payload): array
    {
        $project = $this->findProject($projectId);
        $study = ProjectStudy::query()->where('project_id', $project->id)->findOrFail((string) ($payload['study_id'] ?? ''));

        if ($study->isRunning() && $study->kernel_study_id !== null) {
            try {
                $actor = $study->actor_id !== null ? User::query()->find($study->actor_id) : null;
                $result = $this->kernel->study($actor ?? request()->user(), $study->kernel_study_id);
                $status = (string) ($result['status'] ?? $study->status);
                $study->update([
                    'status' => $status,
                    'events' => $result['events'] ?? $study->events,
                    'agents_md' => $result['agents_md'] ?? $study->agents_md,
                    'notes' => $result['notes'] ?? $study->notes,
                    'error' => $result['error'] ?? $study->error,
                    'finished_at' => in_array($status, ['completed', 'failed', 'cancelled'], true) ? now() : null,
                ]);

                if ($status === 'completed' && filled($study->agents_md)) {
                    $project->update(['agents_md' => $study->agents_md, 'studied_at' => now()]);
                }
            } catch (AiException $exception) {
                $study->update(['status' => 'failed', 'error' => $exception->getMessage(), 'finished_at' => now()]);
            }
        }

        return ['study' => $this->studyPayload($study->refresh())];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cancelStudy(string $projectId, array $payload): array
    {
        $project = $this->findProject($projectId);
        $study = ProjectStudy::query()->where('project_id', $project->id)->findOrFail((string) ($payload['study_id'] ?? ''));

        if ($study->isRunning() && $study->kernel_study_id !== null) {
            try {
                $actor = $study->actor_id !== null ? User::query()->find($study->actor_id) : null;
                $this->kernel->cancelStudy($actor ?? request()->user(), $study->kernel_study_id);
            } catch (AiException) {
                // The runner may already be gone; local state is still cancelled.
            }
        }

        $study->update(['status' => 'cancelled', 'finished_at' => now()]);

        return ['study' => $this->studyPayload($study->refresh())];
    }

    /**
     * @return array<string, mixed>
     */
    private function studyPayload(ProjectStudy $study): array
    {
        return [
            'id' => $study->id,
            'status' => $study->status,
            'events' => $study->events ?? [],
            'notes' => $study->notes,
            'error' => $study->error,
            'started_at' => $study->started_at?->toIso8601String(),
            'finished_at' => $study->finished_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function removeMember(string $projectId, array $payload): array
    {
        $project = $this->findProject($projectId);
        $member = ProjectMember::query()->where('project_id', $project->id)->findOrFail((string) ($payload['member_id'] ?? ''));
        $member->delete();

        return ['removed' => true, 'member_id' => $member->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateTask(string $id, array $payload): array
    {
        $task = $this->findTask($id);
        $data = Validator::make($payload, [
            'client_id' => ['sometimes', 'uuid', 'exists:users,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'string', 'min:3'],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'deadline' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'custom_fields' => ['nullable', 'array'],
        ])->validate();

        $customValues = $data['custom_fields'] ?? [];
        unset($data['custom_fields']);

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->sanitizer->sanitize($data['description']);
        }

        $task->update($data);
        $this->customFields->sync($task, $customValues, request()->user());

        return [...$this->taskSummary($task->refresh()), 'url' => route('admin.tasks.show', $task)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateChecklist(string $id, array $payload): array
    {
        $task = $this->findTask($id);
        $data = Validator::make($payload, [
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['required_with:checklist', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
        ])->validate();

        $task->update(['checklist' => $data['checklist'] ?? []]);

        return [...$this->taskSummary($task->refresh()), 'checklist' => $task->checklist];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function addComment(string $id, array $payload, Request $request): array
    {
        $task = $this->findTask($id);
        $content = Validator::make($payload, ['content' => ['required', 'string', 'min:1', 'max:10000']])->validate()['content'];
        $comment = $task->comments()->create([
            'author_id' => $request->user()->id,
            'content' => $content,
            'attachments' => $payload['attachments'] ?? [],
        ]);

        return ['comment' => [
            'id' => $comment->id,
            'content' => $comment->content,
            'author' => $request->user()->name,
            'created_at' => $comment->created_at?->toIso8601String(),
        ]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function cancelInvoice(string $id, array $arguments): array
    {
        $this->confirm($arguments, 'Отмена счёта');
        $invoice = Invoice::query()->findOrFail($id);
        $this->cancelInvoice->execute($invoice);

        return $this->invoiceSummary($invoice->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function refundInvoice(string $id, array $arguments): array
    {
        $this->confirm($arguments, 'Возврат платежа');
        $invoice = Invoice::query()->findOrFail($id);
        $this->refundInvoice->execute($invoice);

        return $this->invoiceSummary($invoice->refresh());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function storeTaskTemplate(array $payload): array
    {
        $data = $this->validateTaskTemplate($payload);
        $template = $this->taskTemplates->create($data, request()->user());

        return ['template' => $this->templatePayload($template)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateTaskTemplate(string $id, array $payload): array
    {
        $template = TaskTemplate::query()->findOrFail($id);
        $data = $this->validateTaskTemplate($payload);
        $this->taskTemplates->update($template, $data);

        return ['template' => $this->templatePayload($template->refresh())];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyTaskTemplate(string $id, array $payload): array
    {
        $template = TaskTemplate::query()->findOrFail($id);
        $data = Validator::make($payload, [
            'client_id' => ['required', 'uuid', 'exists:users,id'],
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
            'deadline' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $client = $this->findClient($data['client_id']);
        $task = $this->taskTemplates->apply($template, $client, request()->user(), $data);

        return [...$this->taskSummary($task), 'url' => route('admin.tasks.show', $task)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function revokeInvite(string $projectId, array $payload): array
    {
        $project = $this->findProject($projectId);
        $invite = ProjectInvite::query()->where('project_id', $project->id)->findOrFail((string) ($payload['invite_id'] ?? ''));
        $invite->delete();

        return ['revoked' => true, 'invite_id' => $invite->id];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteTaskTemplate(string $id, array $arguments): array
    {
        $this->confirm($arguments, 'Удаление шаблона задачи');
        TaskTemplate::query()->findOrFail($id)->delete();

        return ['deleted' => true, 'id' => $id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function reorderCustomFields(array $payload): array
    {
        $data = Validator::make($payload, [
            'ids' => ['required', 'array'],
            'ids.*' => ['uuid', 'exists:custom_fields,id'],
        ])->validate();

        foreach ($data['ids'] as $index => $id) {
            CustomField::query()->whereKey($id)->update(['sort_order' => $index + 1]);
        }

        return ['reordered' => true, 'ids' => $data['ids']];
    }

    /**
     * @return array<string, mixed>
     */
    private function toggleTaskTemplate(string $id): array
    {
        $template = TaskTemplate::query()->findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);

        return ['template' => $this->templatePayload($template->refresh())];
    }

    /**
     * @return array<string, mixed>
     */
    private function toggleFormTemplate(string $id): array
    {
        $form = Str::isUuid($id)
            ? FormTemplate::query()->findOrFail($id)
            : FormTemplate::query()->where('key', $id)->firstOrFail();
        $form->update(['is_active' => ! $form->is_active]);

        return ['form' => ['id' => $form->id, 'key' => $form->key, 'name' => $form->name, 'is_active' => $form->is_active]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function listCustomFields(array $payload): array
    {
        $entity = filled($payload['entity_type'] ?? null)
            ? CustomFieldEntity::from((string) $payload['entity_type'])
            : null;

        $fields = $entity !== null
            ? $this->customFields->definitions($entity, false)
            : CustomField::query()->orderBy('entity_type')->orderBy('sort_order')->get();

        return ['fields' => $fields->map(fn (CustomField $field): array => [
            'id' => $field->id,
            'entity_type' => $field->entity_type->value,
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'is_required' => $field->is_required,
            'is_active' => $field->is_active,
            'options' => $field->options,
        ])->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function storeCustomField(array $payload): array
    {
        $data = $this->validateCustomField($payload);
        $key = trim((string) ($data['key'] ?? '')) ?: 'field_'.Str::lower(Str::random(6));
        $this->customFields->assertKeyAvailable(CustomFieldEntity::from($data['entity_type']), $key);

        $field = CustomField::create([
            ...$data,
            'key' => $key,
            'entity_type' => CustomFieldEntity::from($data['entity_type']),
            'options' => $data['options'] ?? null,
            'default_value' => $data['default_value'] ?? null,
            'sort_order' => $data['sort_order'] ?? ((int) CustomField::query()->forEntity(CustomFieldEntity::from($data['entity_type']))->max('sort_order') + 1),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'show_in_list' => (bool) ($data['show_in_list'] ?? false),
            'client_editable' => (bool) ($data['client_editable'] ?? false),
            'created_by' => request()->user()?->id,
        ]);

        return ['field' => ['id' => $field->id, 'key' => $field->key, 'label' => $field->label, 'entity_type' => $field->entity_type->value]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateCustomField(string $id, array $payload): array
    {
        $field = CustomField::query()->findOrFail($id);
        $data = $this->validateCustomField($payload);
        $key = trim((string) ($data['key'] ?? '')) ?: $field->key;
        $this->customFields->assertKeyAvailable(CustomFieldEntity::from($data['entity_type']), $key, $field->id);

        $field->update([
            ...$data,
            'key' => $key,
            'entity_type' => CustomFieldEntity::from($data['entity_type']),
            'options' => $data['options'] ?? null,
            'default_value' => $data['default_value'] ?? null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'show_in_list' => (bool) ($data['show_in_list'] ?? false),
            'client_editable' => (bool) ($data['client_editable'] ?? false),
        ]);

        return ['field' => ['id' => $field->id, 'key' => $field->key, 'label' => $field->label, 'entity_type' => $field->entity_type->value]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteCustomField(string $id, array $arguments): array
    {
        $this->confirm($arguments, 'Удаление пользовательского поля');
        $field = CustomField::query()->findOrFail($id);
        $field->delete();

        return ['deleted' => true, 'id' => $id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function setCustomField(string $id, array $payload): array
    {
        $data = Validator::make($payload, [
            'entity_type' => ['required', Rule::enum(CustomFieldEntity::class)],
            'entity_id' => ['required', 'uuid'],
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ])->validate();

        $entity = CustomFieldEntity::from($data['entity_type']);
        $model = $entity->modelClass()::query()->findOrFail($data['entity_id']);
        $result = $this->customFields->setValue($model, $data['key'], $data['value']);

        return ['field' => $data['key'], 'value' => $result->value ?? null, 'entity_id' => $data['entity_id']];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return [
            'project' => $this->settings->project(),
            'transfer' => $this->settings->transferFields(),
            'transfer_configured' => $this->settings->hasTransferDetails(),
            'note' => 'Секреты, API-токены и ключи платёжного шлюза через MCP не читаются и не изменяются.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateTaskTemplate(array $data): array
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
            'default_type' => ['required', Rule::enum(TaskType::class)],
            'default_priority' => ['required', Rule::enum(TaskPriority::class)],
            'default_status' => ['required', 'in:pending_review,queued'],
            'default_assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'default_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_shared' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateCustomField(array $data): array
    {
        return Validator::make($data, [
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
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function confirm(array $arguments, string $action): void
    {
        if (($arguments['confirm'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'confirm' => $action.': передайте confirm=true, чтобы подтвердить действие.',
            ]);
        }
    }

    private function findClient(string $value): User
    {
        $client = Str::isUuid($value)
            ? User::query()->where('role', UserRole::Client)->find($value)
            : User::query()->where('role', UserRole::Client)->where('email', $value)->first();

        return $client ?? throw (new ModelNotFoundException)->setModel(User::class, [$value]);
    }

    private function findProject(string $value): Project
    {
        $project = Str::isUuid($value)
            ? Project::query()->find($value)
            : Project::query()->where('key', mb_strtoupper($value))->first();

        return $project ?? throw (new ModelNotFoundException)->setModel(Project::class, [$value]);
    }

    private function findTask(string $value): Task
    {
        if (Str::isUuid($value)) {
            return Task::query()->findOrFail($value);
        }

        if (preg_match('/^([a-z0-9]+)-(\d+)$/i', $value, $matches) === 1) {
            $task = Task::query()
                ->whereHas('project', fn ($q) => $q->where('key', mb_strtoupper($matches[1])))
                ->where('number', (int) $matches[2])
                ->first();

            if ($task !== null) {
                return $task;
            }
        }

        throw (new ModelNotFoundException)->setModel(Task::class, [$value]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientSummary(User $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'is_active' => $client->is_active,
            'tasks_count' => $client->clientTasks_count ?? $client->clientTasks()->count(),
            'invoices_count' => $client->invoices_count ?? $client->invoices()->count(),
            'url' => route('admin.clients.show', $client),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectSummary(Project $project): array
    {
        return [
            'id' => $project->id,
            'key' => $project->key,
            'name' => $project->name,
            'status' => $project->status->value,
            'priority' => $project->priority->value,
            'client' => $project->client?->only(['id', 'name']),
            'site_url' => $project->site_url,
            'tasks_count' => $project->tasks_count ?? $project->tasks()->count(),
            'members_count' => $project->members_count ?? $project->members()->count(),
            'archived_at' => $project->archived_at?->toIso8601String(),
            'url' => route('admin.projects.show', $project),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskSummary(Task $task): array
    {
        return [
            'id' => $task->id,
            'reference' => $task->reference(),
            'title' => $task->title,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'priority' => $task->priority->value,
            'client' => $task->client?->only(['id', 'name']),
            'project' => $task->project?->only(['id', 'key', 'name']),
            'assignee' => $task->assignee?->only(['id', 'name']),
            'deadline' => $task->deadline?->toDateString(),
            'budget' => $task->budget,
            'url' => route('admin.tasks.show', $task),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status->value,
            'payment_method' => $invoice->payment_method->value,
            'description' => $invoice->description,
            'client' => $invoice->client?->only(['id', 'name']),
            'task' => $invoice->task?->only(['id', 'title']),
            'is_recurring' => $invoice->is_recurring,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'url' => route('admin.invoices.show', $invoice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportSummary(TaskReport $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'display_title' => $report->title ?: 'Отчёт от '.$report->sent_at?->format('d.m.Y'),
            'recipient' => $report->recipient?->only(['id', 'name', 'email']),
            'task' => $report->task?->only(['id', 'title']),
            'items_count' => $report->items_count ?? $report->items()->count(),
            'sent_at' => $report->sent_at?->toIso8601String(),
            'read_at' => $report->read_at?->toIso8601String(),
            'url' => route('reports.show', $report),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(ProjectMember $member): array
    {
        return [
            'id' => $member->id,
            'user' => $member->user?->only(['id', 'name', 'email']),
            'role' => $member->role->value,
            'role_label' => $member->role->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(TaskTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'summary' => $template->summary,
            'client_id' => $template->client_id,
            'default_type' => $template->default_type?->value,
            'default_priority' => $template->default_priority?->value,
            'default_status' => $template->default_status?->value,
            'checklist' => $template->normalizedChecklist(),
            'is_active' => $template->is_active,
            'usage_count' => $template->usage_count,
        ];
    }
}
