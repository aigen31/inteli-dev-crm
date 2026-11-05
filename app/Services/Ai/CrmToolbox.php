<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Actions\Client\CreateClientAction;
use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\MarkInvoiceAsPaidAction;
use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\CustomFieldEntity;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\AiDocument;
use App\Models\CustomField;
use App\Models\FormTemplate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\CustomFieldService;
use App\Services\FormTemplateService;
use App\Services\TaskTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmToolbox
{
    public function __construct(
        private readonly CrmSerializer $serializer,
        private readonly CustomFieldService $customFields,
        private readonly TaskTemplateService $templates,
        private readonly FormTemplateService $formTemplates,
        private readonly KernelClient $kernel,
        private readonly ProjectContext $project,
        private readonly CreateTaskAction $createTask,
        private readonly ChangeTaskStatusAction $changeTaskStatus,
        private readonly CreateClientAction $createClient,
        private readonly CreateInvoiceAction $createInvoice,
        private readonly MarkInvoiceAsPaidAction $markInvoiceAsPaid,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(User $user): array
    {
        $definitions = [];

        foreach ($this->catalog() as $name => $tool) {
            if (! in_array($user->role->value, $tool['roles'], true)) {
                continue;
            }

            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $tool['description'],
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $tool['properties'],
                        'required' => $tool['required'],
                        'additionalProperties' => false,
                    ],
                ],
            ];
        }

        return $definitions;
    }

    public function requiresConfirmation(string $name): bool
    {
        return (bool) ($this->catalog()[$name]['confirm'] ?? false);
    }

    /**
     * Some confirmations are a structured form, not a yes/no. The UI renders
     * the form inside the chat and posts edited values back on confirm.
     */
    public function isFormTool(string $name): bool
    {
        return $name === 'create_task_from_form';
    }

    public function describesForm(string $name): bool
    {
        return $this->isFormTool($name);
    }

    public function isPlanTool(string $name): bool
    {
        return $name === 'propose_task_plan';
    }

    public function describesPlan(string $name): bool
    {
        return $this->isPlanTool($name);
    }

    public function allowsArgumentOverride(string $name): bool
    {
        return $this->isFormTool($name) || $this->isPlanTool($name);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function summarize(string $name, array $arguments): string
    {
        return match ($name) {
            'create_invoice' => 'создать счёт на '.number_format((float) ($arguments['amount'] ?? 0), 0, ',', ' ').' ₽',
            'mark_invoice_paid' => 'подтвердить оплату счёта '.($arguments['invoice_id'] ?? ''),
            'create_client' => 'создать клиента «'.($arguments['name'] ?? '').'»',
            'create_task' => 'создать задачу «'.($arguments['title'] ?? '').'»',
            'create_task_from_form' => 'собрать заявку по форме'.(isset($arguments['title']) && $arguments['title'] !== ''
                ? ' «'.$arguments['title'].'»'
                : ''),
            'propose_task_plan' => 'создать план'.(isset($arguments['tasks']) && is_array($arguments['tasks'])
                ? ' из '.count($arguments['tasks']).' задач'
                : ' задач'),
            'read_document' => 'прочитать документ',
            'study_project_site' => 'изучить сайт проекта',
            'apply_template' => 'создать задачу из шаблона',
            'set_custom_field' => 'изменить поле «'.($arguments['key'] ?? '').'»',
            default => $name,
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, User $user, array $arguments): array
    {
        $tool = $this->catalog()[$name] ?? null;

        if ($tool === null) {
            throw ValidationException::withMessages(['tool' => "Инструмент «{$name}» не найден."]);
        }

        if (! in_array($user->role->value, $tool['roles'], true)) {
            throw ValidationException::withMessages(['tool' => 'Недостаточно прав для этого действия.']);
        }

        return match ($name) {
            'overview' => $this->overview($user),
            'list_clients' => $this->listClients($user, $arguments),
            'list_projects' => $this->listProjects($user),
            'get_client' => $this->getClient($user, $arguments),
            'create_client' => $this->createClient($user, $arguments),
            'list_tasks' => $this->listTasks($user, $arguments),
            'get_task' => $this->getTask($user, $arguments),
            'create_task' => $this->createTaskTool($user, $arguments),
            'create_task_from_form' => $this->createTaskFromForm($user, $arguments),
            'list_form_templates' => $this->listFormTemplates($user),
            'read_document' => $this->readDocument($user, $arguments),
            'propose_task_plan' => $this->createPlanTasks($user, $arguments),
            'study_project_site' => $this->studyProjectSite($user, $arguments),
            'update_task_status' => $this->updateTaskStatus($user, $arguments),
            'list_task_templates' => $this->listTemplates($user),
            'apply_task_template' => $this->applyTemplate($user, $arguments),
            'create_task_template' => $this->createTemplate($user, $arguments),
            'list_invoices' => $this->listInvoices($user, $arguments),
            'create_invoice' => $this->createInvoiceTool($user, $arguments),
            'mark_invoice_paid' => $this->markInvoicePaid($user, $arguments),
            'list_custom_fields' => $this->listCustomFields($arguments),
            'set_custom_field' => $this->setCustomField($user, $arguments),
            'get_payment_link' => $this->paymentLink($user, $arguments),
            default => throw ValidationException::withMessages(['tool' => "Инструмент «{$name}» не реализован."]),
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            'overview' => [
                'description' => 'Сводка по CRM: клиенты, задачи по статусам, просрочки, счета и доход. Вызывай первым для общих вопросов «как дела», «что сегодня».',
                'properties' => (object) [],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'list_clients' => [
                'description' => 'Найти клиентов по имени, email или телефону. Только для администратора.',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Поисковая строка (необязательно).'],
                    'limit' => ['type' => 'integer', 'description' => 'Сколько вернуть, 1–50. По умолчанию 20.'],
                ],
                'required' => [],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'get_client' => [
                'description' => 'Карточка клиента с последними задачами и счетами.',
                'properties' => [
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента или email.'],
                ],
                'required' => ['client_id'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'create_client' => [
                'description' => 'Создать клиентский аккаунт. Пароль можно не указывать — сгенерируется временный.',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Имя клиента.'],
                    'email' => ['type' => 'string', 'description' => 'Email клиента.'],
                    'phone' => ['type' => 'string', 'description' => 'Телефон (необязательно).'],
                    'password' => ['type' => 'string', 'description' => 'Пароль, минимум 8 символов (необязательно).'],
                ],
                'required' => ['name', 'email'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'list_tasks' => [
                'description' => 'Список задач с фильтрами. Клиент видит только свои задачи.',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => array_column(TaskStatus::cases(), 'value'), 'description' => 'Статус задачи.'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента (только администратор).'],
                    'query' => ['type' => 'string', 'description' => 'Поиск по названию.'],
                    'limit' => ['type' => 'integer', 'description' => 'Сколько вернуть, 1–50.'],
                ],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'get_task' => [
                'description' => 'Подробности задачи: описание, чек-лист, комментарии, статус, клиент.',
                'properties' => [
                    'task_id' => ['type' => 'string', 'description' => 'UUID задачи.'],
                ],
                'required' => ['task_id'],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'create_task' => [
                'description' => 'Создать задачу. Клиент создаёт заявку только себе; администратор указывает client_id.',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Название задачи.'],
                    'description' => ['type' => 'string', 'description' => 'Описание задачи (необязательно).'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента (для администратора).'],
                    'template_id' => ['type' => 'string', 'description' => 'UUID шаблона, если задачу нужно создать по шаблону.'],
                    'priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value'), 'description' => 'Приоритет.'],
                    'deadline' => ['type' => 'string', 'description' => 'Дедлайн в формате YYYY-MM-DD (необязательно).'],
                    'assignee_id' => ['type' => 'string', 'description' => 'UUID исполнителя (необязательно).'],
                ],
                'required' => ['title'],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'list_form_templates' => [
                'description' => 'Формы заявок: ключи, названия и схема полей (обязательные, типы, варианты, repeat-группы). Вызывай перед create_task_from_form, чтобы заполнить поля по схеме.',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Поиск по названию или ключу (необязательно).'],
                ],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'create_task_from_form' => [
                'description' => 'Создать заявку-задачу по structured-форме. Сначала вызови list_form_templates и заполни form_data по схеме. Пользователь проверит и дополнит форму в чате перед подтверждением.',
                'properties' => [
                    'form_template_id' => ['type' => 'string', 'description' => 'UUID формы.'],
                    'form_key' => ['type' => 'string', 'description' => 'Ключ формы, если UUID неизвестен, например bug-report.'],
                    'title' => ['type' => 'string', 'description' => 'Короткое название заявки.'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента (только администратор).'],
                    'priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value'), 'description' => 'Приоритет.'],
                    'deadline' => ['type' => 'string', 'description' => 'Желаемый срок YYYY-MM-DD (необязательно).'],
                    'form_data' => [
                        'type' => 'object',
                        'description' => 'Значения полей: {fields: {key: value}, groups: {key: [{...}]}}.',
                        'properties' => [
                            'fields' => ['type' => 'object', 'additionalProperties' => true],
                            'groups' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                    ],
                ],
                'required' => ['form_data'],
                'roles' => ['admin', 'client'],
                'confirm' => true,
            ],
            'read_document' => [
                'description' => 'Прочитать фрагмент приложенного документа. В сообщении есть манифест с id и превью. Читай по частям (offset/limit) или ищи по словам (query), чтобы не тратить контекст. Без document_id берётся последний документ пользователя.',
                'properties' => [
                    'document_id' => ['type' => 'string', 'description' => 'UUID документа (необязательно).'],
                    'query' => ['type' => 'string', 'description' => 'Слова для поиска по строкам (необязательно).'],
                    'offset' => ['type' => 'integer', 'description' => 'С какого символа читать.'],
                    'limit' => ['type' => 'integer', 'description' => 'Сколько символов вернуть, 1000–12000.'],
                ],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'list_projects' => [
                'description' => 'Проекты, к которым можно привязать задачи: ключ, название, клиент, число задач, сайт, есть ли AGENTS.md.',
                'properties' => (object) [],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'propose_task_plan' => [
                'description' => 'Предложить план задач из документа или обсуждения. Вызывай один раз со всеми задачами: пользователь проверит и создаст их. Если не хватает данных — сначала задай 1–3 вопроса в тексте и не вызывай инструмент.',
                'properties' => [
                    'summary' => ['type' => 'string', 'description' => 'О чём план, одной фразой.'],
                    'approach' => ['type' => 'string', 'enum' => ['form', 'template', 'free'], 'description' => 'Как собран план: по форме, по шаблону или свободно.'],
                    'source_document_id' => ['type' => 'string', 'description' => 'UUID документа-источника (необязательно).'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента (только администратор).'],
                    'project_id' => ['type' => 'string', 'description' => 'UUID проекта, в который создать задачи. Посмотри list_projects.'],
                    'questions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Открытые вопросы к пользователю (необязательно).'],
                    'tasks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'checklist' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value')],
                                'due_days' => ['type' => 'integer'],
                                'template_id' => ['type' => 'string'],
                            ],
                            'required' => ['title'],
                        ],
                    ],
                ],
                'required' => ['tasks'],
                'roles' => ['admin', 'client'],
                'confirm' => true,
            ],
            'study_project_site' => [
                'description' => 'Запустить изучение сайта проекта через DeepSeek Harness. Используй, если для задачи не хватает знания о сайте: структура, услуги, цены, термины. Изучение идёт в фоне; результат появится в настройках проекта.',
                'properties' => [
                    'url' => ['type' => 'string', 'description' => 'URL сайта; по умолчанию сайт проекта.'],
                    'focus' => ['type' => 'string', 'description' => 'Что именно изучить: услуги, цены, страницы, термины.'],
                    'project_id' => ['type' => 'string', 'description' => 'UUID проекта; по умолчанию проект диалога.'],
                ],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'update_task_status' => [
                'description' => 'Перевести задачу в другой статус. Только администратор.',
                'properties' => [
                    'task_id' => ['type' => 'string', 'description' => 'UUID задачи.'],
                    'status' => ['type' => 'string', 'enum' => array_column(TaskStatus::cases(), 'value'), 'description' => 'Новый статус.'],
                    'comment' => ['type' => 'string', 'description' => 'Комментарий к смене статуса (необязательно).'],
                ],
                'required' => ['task_id', 'status'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'list_task_templates' => [
                'description' => 'Доступные шаблоны задач. Клиент видит только опубликованные общие шаблоны.',
                'properties' => (object) [],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'apply_task_template' => [
                'description' => 'Создать задачу из шаблона для клиента. Только администратор.',
                'properties' => [
                    'template_id' => ['type' => 'string', 'description' => 'UUID шаблона.'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента.'],
                    'title' => ['type' => 'string', 'description' => 'Переопределить название (необязательно).'],
                    'deadline' => ['type' => 'string', 'description' => 'Дедлайн YYYY-MM-DD (необязательно).'],
                    'assignee_id' => ['type' => 'string', 'description' => 'UUID исполнителя (необязательно).'],
                ],
                'required' => ['template_id', 'client_id'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'create_task_template' => [
                'description' => 'Создать шаблон задачи: описание, чек-лист, приоритет, срок по умолчанию.',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Название шаблона.'],
                    'summary' => ['type' => 'string', 'description' => 'Короткое описание.'],
                    'description' => ['type' => 'string', 'description' => 'Подробное описание (HTML или текст).'],
                    'checklist' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Пункты чек-листа.'],
                    'default_priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value')],
                    'default_due_days' => ['type' => 'integer', 'description' => 'Срок в днях от создания.'],
                ],
                'required' => ['name'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'list_invoices' => [
                'description' => 'Список счетов. Клиент видит только свои счета.',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => array_column(InvoiceStatus::cases(), 'value')],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента (только администратор).'],
                    'limit' => ['type' => 'integer'],
                ],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'create_invoice' => [
                'description' => 'Создать счёт по задаче. Требует подтверждения пользователя.',
                'properties' => [
                    'task_id' => ['type' => 'string', 'description' => 'UUID задачи.'],
                    'amount' => ['type' => 'number', 'description' => 'Сумма в рублях.'],
                    'description' => ['type' => 'string', 'description' => 'Назначение счёта.'],
                    'payment_method' => ['type' => 'string', 'enum' => array_column(PaymentMethod::cases(), 'value')],
                ],
                'required' => ['task_id', 'amount', 'description'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'mark_invoice_paid' => [
                'description' => 'Подтвердить прямой перевод и отметить счёт оплаченным. Требует подтверждения.',
                'properties' => [
                    'invoice_id' => ['type' => 'string', 'description' => 'UUID счёта.'],
                ],
                'required' => ['invoice_id'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'list_custom_fields' => [
                'description' => 'Определения пользовательских полей: ключи, типы, варианты, обязательность.',
                'properties' => [
                    'entity_type' => ['type' => 'string', 'enum' => array_column(CustomFieldEntity::cases(), 'value'), 'description' => 'client, task или invoice.'],
                ],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'set_custom_field' => [
                'description' => 'Заполнить пользовательское поле у клиента, задачи или счёта. Только администратор.',
                'properties' => [
                    'entity_type' => ['type' => 'string', 'enum' => array_column(CustomFieldEntity::cases(), 'value')],
                    'entity_id' => ['type' => 'string', 'description' => 'UUID сущности.'],
                    'key' => ['type' => 'string', 'description' => 'Ключ поля, например intake_status.'],
                    'value' => ['description' => 'Значение: строка, число, дата, массив или boolean.'],
                ],
                'required' => ['entity_type', 'entity_id', 'key', 'value'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'get_payment_link' => [
                'description' => 'Ссылка на счёт клиента для оплаты. Только для клиента.',
                'properties' => [
                    'invoice_id' => ['type' => 'string', 'description' => 'UUID счёта.'],
                ],
                'required' => ['invoice_id'],
                'roles' => ['client'],
                'confirm' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(User $user): array
    {
        if ($user->isAdmin()) {
            $active = [
                TaskStatus::PendingReview,
                TaskStatus::Queued,
                TaskStatus::InProgress,
                TaskStatus::AwaitingReview,
                TaskStatus::RejectedByClient,
            ];

            return [
                'role' => 'admin',
                'clients' => User::query()->where('role', 'client')->count(),
                'tasks_active' => Task::query()->whereIn('status', $active)->count(),
                'tasks_overdue' => Task::query()
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<', today())
                    ->whereIn('status', $active)
                    ->count(),
                'invoices_pending' => Invoice::query()->where('status', InvoiceStatus::Pending)->count(),
                'invoices_pending_amount' => (float) Invoice::query()->where('status', InvoiceStatus::Pending)->sum('amount'),
                'revenue_month' => (float) Invoice::query()
                    ->where('status', InvoiceStatus::Paid)
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount'),
                'recent_tasks' => Task::query()->with('client')->latest()->limit(5)->get()
                    ->map(fn (Task $task) => $this->serializer->task($task))->all(),
            ];
        }

        return [
            'role' => 'client',
            'tasks_active' => $user->clientTasks()->whereIn('status', [
                TaskStatus::PendingReview,
                TaskStatus::Queued,
                TaskStatus::InProgress,
                TaskStatus::AwaitingReview,
                TaskStatus::RejectedByClient,
            ])->count(),
            'invoices_pending' => $user->invoices()->where('status', InvoiceStatus::Pending)->count(),
            'invoices_pending_amount' => (float) $user->invoices()->where('status', InvoiceStatus::Pending)->sum('amount'),
            'recent_tasks' => $user->clientTasks()->latest()->limit(5)->get()
                ->map(fn (Task $task) => $this->serializer->task($task))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listClients(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 50);
        $query = User::query()->where('role', UserRole::Client);

        if (($search = trim((string) ($arguments['query'] ?? ''))) !== '') {
            $query->where(fn ($q) => $q
                ->where('name', $this->likeOperator(), "%{$search}%")
                ->orWhere('email', $this->likeOperator(), "%{$search}%")
                ->orWhere('phone', $this->likeOperator(), "%{$search}%"));
        }

        $clients = $query->latest()->limit($limit)->get();

        return [
            'count' => $clients->count(),
            'items' => $clients->map(fn (User $client) => $this->serializer->client($client))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getClient(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $client = $this->resolveClient($user, (string) ($arguments['client_id'] ?? ''));

        return $this->serializer->client($client, true);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createClient(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
        ])->validate();

        $password = $data['password'] ?? Str::password(12);
        $client = $this->createClient->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $password,
        ]);

        return [
            ...$this->serializer->client($client),
            'temporary_password' => $password,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listTasks(User $user, array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 50);
        $query = Task::query()->with(['client', 'assignee', 'template']);

        if ($user->isClient()) {
            $query->visibleTo($user);
        } elseif (isset($arguments['client_id']) && $arguments['client_id'] !== '') {
            $query->where('client_id', $arguments['client_id']);
        }

        if (isset($arguments['status']) && $arguments['status'] !== '') {
            $status = TaskStatus::tryFrom((string) $arguments['status']);

            if ($status === null) {
                throw ValidationException::withMessages(['status' => 'Неизвестный статус задачи.']);
            }

            $query->where('status', $status);
        }

        if (($search = trim((string) ($arguments['query'] ?? ''))) !== '') {
            $query->where('title', $this->likeOperator(), "%{$search}%");
        }

        $tasks = $query->latest()->limit($limit)->get();

        return [
            'count' => $tasks->count(),
            'items' => $tasks->map(fn (Task $task) => $this->serializer->task($task))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getTask(User $user, array $arguments): array
    {
        $task = $this->resolveTask($user, (string) ($arguments['task_id'] ?? ''));

        return $this->serializer->task($task);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createTaskTool(User $user, array $arguments): array
    {
        $title = trim((string) ($arguments['title'] ?? ''));

        if (mb_strlen($title) < 3) {
            throw ValidationException::withMessages(['title' => 'Название задачи слишком короткое.']);
        }

        $client = $user->isClient()
            ? $user
            : $this->resolveClient($user, (string) ($arguments['client_id'] ?? ''));

        if (isset($arguments['template_id']) && $arguments['template_id'] !== '') {
            $template = TaskTemplate::query()->visibleTo($user)->find((string) $arguments['template_id']);

            if (! $template instanceof TaskTemplate) {
                throw ValidationException::withMessages(['template_id' => 'Шаблон не найден.']);
            }

            $task = $this->templates->apply($template, $client, $user, [
                'title' => $title,
                'description' => $arguments['description'] ?? null,
                'deadline' => $arguments['deadline'] ?? null,
                'assignee_id' => $arguments['assignee_id'] ?? null,
            ], $user->isClient() ? TaskSource::Client : TaskSource::Ai);
        } else {
            $task = $this->createTask->execute([
                'client_id' => $client->id,
                'title' => $title,
                'description' => (string) ($arguments['description'] ?? 'Задача создана через Кики.'),
                'priority' => $arguments['priority'] ?? TaskPriority::Medium,
                'deadline' => $arguments['deadline'] ?? null,
                'assignee_id' => $arguments['assignee_id'] ?? null,
                'source' => $user->isClient() ? TaskSource::Client : TaskSource::Ai,
            ], $user);
        }

        return $this->serializer->task($task);
    }

    /**
     * @return array<string, mixed>
     */
    private function listFormTemplates(User $user): array
    {
        $templates = FormTemplate::query()
            ->visibleTo($user)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'count' => $templates->count(),
            'items' => $templates->map(fn (FormTemplate $template): array => [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->name,
                'summary' => $template->summary,
                'category' => $template->category,
                'field_count' => $template->fieldCount(),
                'required_count' => $template->requiredCount(),
                'fields' => $this->formFields($template),
                'groups' => $this->formGroups($template),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createTaskFromForm(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'form_template_id' => ['nullable', 'uuid'],
            'form_key' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:128'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
            'form_data' => ['required', 'array'],
        ])->validate();

        $template = $this->resolveFormTemplate($user, $data);
        $client = $user->isClient()
            ? $user
            : $this->resolveClient($user, (string) ($data['client_id'] ?? ''));

        $task = $this->formTemplates->createTask(
            $template,
            $client,
            [
                'title' => $data['title'] ?? $template->name,
                'priority' => $data['priority'] ?? TaskPriority::Medium,
                'deadline' => $data['deadline'] ?? null,
                'form_data' => $data['form_data'],
            ],
            $user,
            $user->isClient() ? TaskSource::Client : TaskSource::Ai,
        );

        return [
            ...$this->serializer->task($task),
            'form_template' => [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->name,
            ],
        ];
    }

    /**
     * Payload that the chat UI renders inside a confirmation card. It is built
     * before the user confirms, so a stale template id fails the tool call and
     * the model can correct itself instead of showing a broken form.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function formPayload(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'form_template_id' => ['nullable', 'uuid'],
            'form_key' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:128'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
            'form_data' => ['nullable', 'array'],
        ])->validate();

        $template = $this->resolveFormTemplate($user, $data);

        return [
            'template' => [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->name,
                'summary' => $template->summary,
                'description' => $template->description,
                'category' => $template->category,
            ],
            'schema' => $template->normalizedSchema(),
            'values' => $this->normalizeFormValues($template, $data['form_data'] ?? []),
            'defaults' => [
                'title' => $data['title'] ?? $template->name,
                'priority' => $data['priority'] ?? TaskPriority::Medium->value,
                'deadline' => $data['deadline'] ?? null,
            ],
            'client_id' => $data['client_id'] ?? ($user->isClient() ? $user->id : null),
            'clients' => $user->isAdmin()
                ? User::query()
                    ->where('role', UserRole::Client)
                    ->orderBy('name')
                    ->limit(200)
                    ->get(['id', 'name', 'email'])
                    ->map(fn (User $client): array => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email,
                    ])
                    ->all()
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveFormTemplate(User $user, array $data): FormTemplate
    {
        $query = FormTemplate::query()->visibleTo($user);

        $template = isset($data['form_template_id']) && $data['form_template_id'] !== ''
            ? $query->find((string) $data['form_template_id'])
            : (isset($data['form_key']) && $data['form_key'] !== ''
                ? $query->where('key', (string) $data['form_key'])->first()
                : null);

        if (! $template instanceof FormTemplate) {
            throw ValidationException::withMessages([
                'form_template_id' => 'Форма не найдена. Вызовите list_form_templates и выберите существующую.',
            ]);
        }

        return $template;
    }

    /**
     * Fill the schema shape so the UI can render a complete, editable form even
     * when the model left optional fields out.
     *
     * @param  array<string, mixed>  $values
     * @return array{fields: array<string, mixed>, groups: array<string, list<array<string, mixed>>>}
     */
    private function normalizeFormValues(FormTemplate $template, array $values): array
    {
        $schema = $template->normalizedSchema();
        $normalized = ['fields' => [], 'groups' => []];
        $inputFields = is_array($values['fields'] ?? null) ? $values['fields'] : [];

        foreach ($schema['fields'] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $normalized['fields'][$key] = $inputFields[$key] ?? ($field['type'] === 'checkbox' ? false : '');
        }

        $inputGroups = is_array($values['groups'] ?? null) ? $values['groups'] : [];

        foreach ($schema['groups'] as $group) {
            $key = (string) ($group['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $rows = is_array($inputGroups[$key] ?? null) ? $inputGroups[$key] : [];
            $normalized['groups'][$key] = array_values(array_map(function (array $row) use ($group): array {
                $clean = [];
                foreach ($group['fields'] ?? [] as $field) {
                    $fieldKey = (string) ($field['key'] ?? '');
                    if ($fieldKey === '') {
                        continue;
                    }
                    $clean[$fieldKey] = $row[$fieldKey] ?? ($field['type'] === 'checkbox' ? false : '');
                }

                return $clean;
            }, array_filter($rows, 'is_array')));
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formFields(FormTemplate $template): array
    {
        return array_map(fn (array $field): array => [
            'key' => $field['key'] ?? null,
            'label' => $field['label'] ?? null,
            'type' => $field['type'] ?? 'text',
            'required' => (bool) ($field['required'] ?? false),
            'options' => array_values(array_map(
                fn (array $option) => $option['value'] ?? null,
                array_filter($field['options'] ?? [], 'is_array'),
            )),
        ], $template->fields());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formGroups(FormTemplate $template): array
    {
        return array_map(fn (array $group): array => [
            'key' => $group['key'] ?? null,
            'label' => $group['label'] ?? null,
            'min' => (int) ($group['min'] ?? 0),
            'max' => (int) ($group['max'] ?? 20),
            'fields' => array_map(fn (array $field): array => [
                'key' => $field['key'] ?? null,
                'label' => $field['label'] ?? null,
                'type' => $field['type'] ?? 'text',
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values(array_map(
                    fn (array $option) => $option['value'] ?? null,
                    array_filter($field['options'] ?? [], 'is_array'),
                )),
            ], array_filter($group['fields'] ?? [], 'is_array')),
        ], $template->groups());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @return list<array<string, mixed>>
     */
    private function projectOptions(User $user): array
    {
        return Project::query()
            ->visibleTo($user)
            ->whereNull('archived_at')
            ->withCount('tasks')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'key', 'name', 'color', 'client_id'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'color' => $project->color,
                'client_id' => $project->client_id,
                'tasks_count' => (int) $project->tasks_count,
            ])
            ->all();
    }

    private function listProjects(User $user): array
    {
        $projects = Project::query()
            ->visibleTo($user)
            ->whereNull('archived_at')
            ->with('client:id,name')
            ->withCount('tasks')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return [
            'count' => $projects->count(),
            'items' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'status' => $project->status->value,
                'client' => $project->client?->name,
                'tasks_count' => (int) $project->tasks_count,
                'site_url' => $project->site_url,
                'has_agents_md' => filled($project->agents_md),
                'studied_at' => $project->studied_at?->toDateString(),
            ])->all(),
        ];
    }

    private function resolveProject(User $user, ?string $projectId): ?Project
    {
        if ($projectId === null || $projectId === '') {
            return null;
        }

        $project = Project::query()
            ->visibleTo($user)
            ->find($projectId);

        if (! $project instanceof Project) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        return $project;
    }

    public function planPayload(User $user, array $arguments): array
    {
        $data = $this->validatedPlan($arguments);
        $project = $this->resolveProject($user, $data['project_id'] ?? null);

        if ($project !== null && ! $project->canCreateTasks($user)) {
            throw ValidationException::withMessages([
                'project_id' => 'В этом проекте у вас только просмотр — создавать задачи нельзя.',
            ]);
        }

        $clientId = $user->isClient() ? $user->id : null;

        if ($user->isAdmin()) {
            $provided = $data['client_id'] ?? null;

            if (is_string($provided) && trim($provided) !== '') {
                try {
                    $clientId = $this->resolveClient($user, $provided)->id;
                } catch (ValidationException) {
                    // The model may confuse project_id with client_id. Do not
                    // surface a stale id to the confirmation form.
                    $clientId = null;
                }
            }

            $clientId ??= $project?->client_id;
        }

        return [
            'summary' => $data['summary'] ?? null,
            'approach' => $data['approach'] ?? 'free',
            'source_document_id' => $data['source_document_id'] ?? null,
            'questions' => $data['questions'] ?? [],
            'client_id' => $clientId,
            'client_required' => $user->isAdmin(),
            'project_id' => $project?->id,
            'project' => $project ? [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'color' => $project->color,
            ] : null,
            'projects' => $this->projectOptions($user),
            'clients' => $user->isAdmin()
                ? User::query()
                    ->where('role', UserRole::Client)
                    ->orderBy('name')
                    ->limit(200)
                    ->get(['id', 'name', 'email'])
                    ->map(fn (User $client): array => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email,
                    ])
                    ->all()
                : [],
            'tasks' => $data['tasks'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createPlanTasks(User $user, array $arguments): array
    {
        $data = $this->validatedPlan($arguments);
        $project = $this->resolveProject($user, $data['project_id'] ?? null);

        if ($project !== null && ! $project->canCreateTasks($user)) {
            throw ValidationException::withMessages([
                'project_id' => 'В этом проекте у вас только просмотр — создавать задачи нельзя.',
            ]);
        }

        $clientId = (string) ($data['client_id'] ?? $project?->client_id ?? '');
        $client = $user->isClient()
            ? $user
            : $this->resolveClient($user, $clientId);

        $created = [];

        foreach ($data['tasks'] as $item) {
            $deadline = isset($item['due_days']) && $item['due_days'] !== null
                ? now()->addDays((int) $item['due_days'])->toDateString()
                : null;

            $templateId = (string) ($item['template_id'] ?? '');

            if ($templateId !== '') {
                $template = TaskTemplate::query()->visibleTo($user)->find($templateId);

                if (! $template instanceof TaskTemplate) {
                    throw ValidationException::withMessages([
                        'tasks' => 'Шаблон задачи не найден: '.$templateId,
                    ]);
                }

                $task = $this->templates->apply($template, $client, $user, [
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'deadline' => $deadline,
                    'project_id' => $project?->id,
                ], TaskSource::Ai);
            } else {
                $task = $this->createTask->execute([
                    'client_id' => $client->id,
                    'project_id' => $project?->id,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? 'Задача из плана Кики.',
                    'checklist' => $item['checklist'] ?? [],
                    'priority' => $item['priority'] ?? TaskPriority::Medium,
                    'deadline' => $deadline,
                    'source' => TaskSource::Ai,
                ], $user);
            }

            $created[] = ['id' => $task->id, 'title' => $task->title];
        }

        $templateId = $data['source_document_id'] ?? null;

        return [
            'summary' => $data['summary'] ?? null,
            'approach' => $data['approach'] ?? 'free',
            'source_document_id' => $templateId,
            'count' => count($created),
            'created' => $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function readDocument(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'document_id' => ['nullable', 'uuid'],
            'query' => ['nullable', 'string', 'max:200'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1000', 'max:12000'],
        ])->validate();

        $query = AiDocument::query()->where('user_id', $user->id);

        if (isset($data['document_id']) && $data['document_id'] !== '') {
            $query->whereKey($data['document_id']);
        }

        $document = $query->latest('created_at')->first();

        if (! $document instanceof AiDocument) {
            throw ValidationException::withMessages([
                'document_id' => 'Документ не найден. Попросите пользователя прикрепить его снова.',
            ]);
        }

        $search = trim((string) ($data['query'] ?? ''));

        if ($search !== '') {
            $found = $this->searchDocument($document, $search);

            return [
                'document_id' => $document->id,
                'name' => $document->name,
                'total_chars' => $document->char_count,
                'mode' => 'query',
                'matches' => $found['matches'],
                'text' => $found['text'],
                'note' => $found['matches'] === 0
                    ? 'По этим словам ничего не нашлось. Попробуй другие формулировки или прочитай документ по offset.'
                    : null,
            ];
        }

        $offset = max(0, (int) ($data['offset'] ?? 0));
        $limit = (int) ($data['limit'] ?? 12000);
        $slice = mb_substr($document->text, $offset, $limit);
        $next = $offset + mb_strlen($slice);

        return [
            'document_id' => $document->id,
            'name' => $document->name,
            'total_chars' => $document->char_count,
            'mode' => 'slice',
            'offset' => $offset,
            'next_offset' => $next < $document->char_count ? $next : null,
            'text' => $slice,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function validatedPlan(array $arguments): array
    {
        $data = Validator::make($arguments, [
            'summary' => ['nullable', 'string', 'max:300'],
            'approach' => ['nullable', 'in:form,template,free'],
            'source_document_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'string', 'max:128'],
            'project_id' => ['nullable', 'uuid'],
            'questions' => ['nullable', 'array', 'max:10'],
            'questions.*' => ['string', 'max:500'],
            'tasks' => ['required', 'array', 'min:1', 'max:40'],
            'tasks.*.title' => ['required', 'string', 'min:3', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:8000'],
            'tasks.*.checklist' => ['nullable', 'array', 'max:30'],
            'tasks.*.checklist.*' => ['string', 'max:500'],
            'tasks.*.priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'tasks.*.due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tasks.*.template_id' => ['nullable', 'uuid'],
        ])->validate();

        $data['tasks'] = array_values(array_map(function (array $task): array {
            $checklist = array_values(array_filter(array_map(
                fn ($item) => trim((string) (is_array($item) ? ($item['text'] ?? '') : $item)),
                $task['checklist'] ?? [],
            ), fn (string $item) => $item !== ''));

            return [
                'title' => trim((string) $task['title']),
                'description' => trim((string) ($task['description'] ?? '')),
                'checklist' => $checklist,
                'priority' => $task['priority'] ?? TaskPriority::Medium->value,
                'due_days' => isset($task['due_days']) ? (int) $task['due_days'] : null,
                'template_id' => $task['template_id'] ?? null,
            ];
        }, $data['tasks']));

        return $data;
    }

    /**
     * @return array{text: string, matches: int}
     */
    private function searchDocument(AiDocument $document, string $query): array
    {
        $lines = preg_split('/\n/u', $document->text) ?: [];
        $terms = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower($query)) ?: [],
            fn (string $term) => mb_strlen($term) >= 2,
        ));

        if ($terms === []) {
            return ['text' => '', 'matches' => 0];
        }

        $hits = [];

        foreach ($lines as $index => $line) {
            $lower = mb_strtolower($line);
            $score = 0;

            foreach ($terms as $term) {
                if (mb_strpos($lower, $term) !== false) {
                    $score++;
                }
            }

            if ($score > 0) {
                $hits[$index] = $score;
            }

            if (count($hits) >= 40) {
                break;
            }
        }

        if ($hits === []) {
            return ['text' => '', 'matches' => 0];
        }

        $blocks = [];
        $used = [];
        $chars = 0;

        foreach (array_keys($hits) as $index) {
            $start = max(0, $index - 1);
            $end = min(count($lines) - 1, $index + 1);
            $block = [];

            for ($lineIndex = $start; $lineIndex <= $end; $lineIndex++) {
                if (isset($used[$lineIndex])) {
                    continue;
                }

                $used[$lineIndex] = true;
                $block[] = ($lineIndex + 1).': '.$lines[$lineIndex];
            }

            if ($block === []) {
                continue;
            }

            $text = implode("\n", $block);

            if ($chars + mb_strlen($text) > 12000) {
                break;
            }

            $blocks[] = $text;
            $chars += mb_strlen($text);
        }

        return ['text' => implode("\n…\n", $blocks), 'matches' => count($hits)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function studyProjectSite(User $user, array $arguments): array
    {
        $project = $this->resolveProject($user, $arguments['project_id'] ?? null);
        $url = trim((string) ($arguments['url'] ?? ''));

        if ($url === '' && $project !== null) {
            $url = (string) $project->site_url;
        }

        if ($url === '') {
            $url = (string) $this->project->siteUrl();
        }

        if ($url === '') {
            throw ValidationException::withMessages([
                'url' => 'Не задан сайт проекта. Укажите его в настройках проекта.',
            ]);
        }

        $local = $project?->studies()->create([
            'actor_id' => $user->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $study = $this->kernel->startStudy($user, [
            'url' => $url,
            'focus' => $arguments['focus'] ?? $project?->name,
            'agents_md' => $project?->agents_md ?? ($this->project->agentsMd() ?: null),
            'instructions' => $project?->instructions ?? ($this->project->instructions() ?: null),
        ]);

        $local?->update([
            'kernel_study_id' => $study['id'] ?? null,
            'events' => $study['events'] ?? [],
        ]);

        return [
            'status' => 'started',
            'study_id' => $study['id'] ?? null,
            'project_id' => $project?->id,
            'url' => $url,
            'message' => 'Изучение сайта запущено. Прогресс и результат — в проекте.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateTaskStatus(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $task = $this->resolveTask($user, (string) ($arguments['task_id'] ?? ''));
        $status = TaskStatus::tryFrom((string) ($arguments['status'] ?? ''));

        if ($status === null) {
            throw ValidationException::withMessages(['status' => 'Неизвестный статус задачи.']);
        }

        $this->changeTaskStatus->execute($task, $status, $user, $arguments['comment'] ?? null);

        return $this->serializer->task($task->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function listTemplates(User $user): array
    {
        $templates = TaskTemplate::query()
            ->visibleTo($user)
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->get();

        return [
            'count' => $templates->count(),
            'items' => $templates->map(fn (TaskTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'summary' => $template->summary,
                'default_priority' => $template->default_priority?->value,
                'default_due_days' => $template->default_due_days,
                'checklist_count' => count($template->normalizedChecklist()),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function applyTemplate(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $template = TaskTemplate::query()->find((string) ($arguments['template_id'] ?? ''));
        $client = $this->resolveClient($user, (string) ($arguments['client_id'] ?? ''));

        if (! $template instanceof TaskTemplate) {
            throw ValidationException::withMessages(['template_id' => 'Шаблон не найден.']);
        }

        $task = $this->templates->apply($template, $client, $user, [
            'title' => $arguments['title'] ?? null,
            'deadline' => $arguments['deadline'] ?? null,
            'assignee_id' => $arguments['assignee_id'] ?? null,
        ], TaskSource::Ai);

        return $this->serializer->task($task);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createTemplate(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'checklist' => ['nullable', 'array', 'max:50'],
            'checklist.*' => ['string', 'max:500'],
            'default_priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'default_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ])->validate();

        $template = $this->templates->create($data, $user);

        return $this->serializer->template($template);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listInvoices(User $user, array $arguments): array
    {
        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 50);
        $query = Invoice::query()->with(['client', 'task']);

        if ($user->isClient()) {
            $query->visibleTo($user);
        } elseif (isset($arguments['client_id']) && $arguments['client_id'] !== '') {
            $query->where('client_id', $arguments['client_id']);
        }

        if (isset($arguments['status']) && $arguments['status'] !== '') {
            $status = InvoiceStatus::tryFrom((string) $arguments['status']);

            if ($status === null) {
                throw ValidationException::withMessages(['status' => 'Неизвестный статус счёта.']);
            }

            $query->where('status', $status);
        }

        $invoices = $query->latest()->limit($limit)->get();

        return [
            'count' => $invoices->count(),
            'items' => $invoices->map(fn (Invoice $invoice) => $this->serializer->invoice($invoice))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createInvoiceTool(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'task_id' => ['required', 'uuid', 'exists:tasks,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'description' => ['required', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ])->validate();

        $invoice = $this->createInvoice->execute($data);

        return $this->serializer->invoice($invoice);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function markInvoicePaid(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $invoice = $this->resolveInvoice($user, (string) ($arguments['invoice_id'] ?? ''));
        $this->markInvoiceAsPaid->execute($invoice);

        return $this->serializer->invoice($invoice->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listCustomFields(array $arguments): array
    {
        $entity = isset($arguments['entity_type'])
            ? CustomFieldEntity::tryFrom((string) $arguments['entity_type'])
            : null;

        $query = CustomField::query()->when($entity, fn ($q) => $q->forEntity($entity))->orderBy('sort_order');
        $fields = $query->get();

        return [
            'count' => $fields->count(),
            'items' => $fields->map(fn (CustomField $field) => $this->serializer->customField($field))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setCustomField(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $entity = CustomFieldEntity::tryFrom((string) ($arguments['entity_type'] ?? ''));

        if ($entity === null) {
            throw ValidationException::withMessages(['entity_type' => 'Неизвестная сущность.']);
        }

        $modelClass = $entity->modelClass();
        $model = $modelClass::query()->find((string) ($arguments['entity_id'] ?? ''));

        if ($model === null) {
            throw ValidationException::withMessages(['entity_id' => 'Запись не найдена.']);
        }

        $result = $this->customFields->setValue($model, (string) ($arguments['key'] ?? ''), $arguments['value'] ?? null);

        return [
            'entity_type' => $entity->value,
            'entity_id' => $model->getKey(),
            'key' => $result->field->key,
            'label' => $result->field->label,
            'value' => $result->value,
            'display' => $result->display,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function paymentLink(User $user, array $arguments): array
    {
        if (! $user->isClient()) {
            throw ValidationException::withMessages(['role' => 'Ссылка доступна только клиенту.']);
        }

        $invoice = $this->resolveInvoice($user, (string) ($arguments['invoice_id'] ?? ''));

        return [
            'invoice_id' => $invoice->id,
            'url' => route('client.invoices.show', $invoice),
            'amount' => (float) $invoice->amount,
            'status' => $invoice->status->label(),
        ];
    }

    private function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw ValidationException::withMessages(['role' => 'Действие доступно только администратору.']);
        }
    }

    private function resolveClient(User $user, string $identifier): User
    {
        if ($user->isClient()) {
            return $user;
        }

        $identifier = trim($identifier);

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'client_id' => 'Укажите клиента: администратор создаёт заявку на конкретного клиента.',
            ]);
        }

        $client = User::query()
            ->where('role', UserRole::Client)
            ->where(function ($query) use ($identifier): void {
                $query->where('email', $identifier);

                if (Str::isUuid($identifier)) {
                    $query->orWhereKey($identifier);
                }
            })
            ->first();

        if (! $client instanceof User) {
            throw ValidationException::withMessages(['client_id' => 'Клиент не найден.']);
        }

        return $client;
    }

    private function resolveTask(User $user, string $id): Task
    {
        $task = Task::query()
            ->when($user->isClient(), fn ($query) => $query->visibleTo($user))
            ->find($id);

        if (! $task instanceof Task) {
            throw ValidationException::withMessages(['task_id' => 'Задача не найдена или недоступна.']);
        }

        return $task;
    }

    private function resolveInvoice(User $user, string $id): Invoice
    {
        $invoice = Invoice::query()
            ->when($user->isClient(), fn ($query) => $query->where('client_id', $user->id))
            ->find($id);

        if (! $invoice instanceof Invoice) {
            throw ValidationException::withMessages(['invoice_id' => 'Счёт не найден или недоступен.']);
        }

        return $invoice;
    }
}
