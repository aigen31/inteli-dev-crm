<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Actions\Client\CreateClientAction;
use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\MarkInvoiceAsPaidAction;
use App\Actions\Task\ChangeTaskStatusAction;
use App\Actions\Task\CreateTaskAction;
use App\Enums\BriefStatus;
use App\Enums\CustomFieldEntity;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Models\AiDocument;
use App\Models\Brief;
use App\Models\BriefTemplate;
use App\Models\CustomField;
use App\Models\FormTemplate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\TaskReportItem;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Briefs\BriefFormatter;
use App\Services\Briefs\BriefSchema;
use App\Services\Briefs\BriefService;
use App\Services\CustomFieldService;
use App\Services\FormTemplateService;
use App\Services\HtmlSanitizer;
use App\Services\ProjectInviteService;
use App\Services\ProjectService;
use App\Services\ReportService;
use App\Services\Tasks\TaskStrictControl;
use App\Services\TaskTemplateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmToolbox
{
    public function __construct(
        private readonly CrmSerializer $serializer,
        private readonly HtmlSanitizer $sanitizer,
        private readonly CustomFieldService $customFields,
        private readonly TaskTemplateService $templates,
        private readonly FormTemplateService $formTemplates,
        private readonly KernelClient $kernel,
        private readonly ProjectContext $project,
        private readonly CrmHelpService $help,
        private readonly CreateTaskAction $createTask,
        private readonly ChangeTaskStatusAction $changeTaskStatus,
        private readonly CreateClientAction $createClient,
        private readonly CreateInvoiceAction $createInvoice,
        private readonly MarkInvoiceAsPaidAction $markInvoiceAsPaid,
        private readonly ReportService $reports,
        private readonly ProjectService $projects,
        private readonly ProjectInviteService $invites,
        private readonly BriefFormatter $briefs,
        private readonly BriefService $briefService,
        private readonly TaskStrictControl $strictControl,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(User $user): array
    {
        $definitions = [];

        foreach ($this->catalog() as $name => $tool) {
            if (array_intersect($user->roleNames(), $tool['roles']) === []) {
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
            'create_brief' => 'создать бриф «'.($arguments['title'] ?? $arguments['template'] ?? '').'»',
            'update_brief' => 'переименовать бриф в «'.($arguments['title'] ?? '').'»',
            'create_brief_template' => 'создать шаблон брифа «'.($arguments['title'] ?? '').'»',
            'update_brief_template' => 'изменить шаблон брифа «'.($arguments['title'] ?? $arguments['template'] ?? '').'»',
            'delete_brief_template' => 'удалить шаблон брифа «'.($arguments['template'] ?? '').'»',
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

        if (array_intersect($user->roleNames(), $tool['roles']) === []) {
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
            'update_task' => $this->updateTask($user, $arguments),
            'distribute_project_budget' => $this->distributeProjectBudget($user, $arguments),
            'update_tasks' => $this->updateTasks($user, $arguments),
            'list_task_templates' => $this->listTemplates($user),
            'apply_task_template' => $this->applyTemplate($user, $arguments),
            'create_task_template' => $this->createTemplate($user, $arguments),
            'list_invoices' => $this->listInvoices($user, $arguments),
            'create_invoice' => $this->createInvoiceTool($user, $arguments),
            'mark_invoice_paid' => $this->markInvoicePaid($user, $arguments),
            'list_custom_fields' => $this->listCustomFields($arguments),
            'set_custom_field' => $this->setCustomField($user, $arguments),
            'get_payment_link' => $this->paymentLink($user, $arguments),
            'crm_help' => $this->crmHelp($user, $arguments),
            'create_report' => $this->createReport($user, $arguments),
            'list_brief_templates' => $this->listBriefTemplates($user),
            'update_brief' => $this->updateBrief($user, $arguments),
            'create_brief' => $this->createBrief($user, $arguments),
            'research' => $this->research($user, $arguments),
            'research_status' => $this->researchStatus($user, $arguments),
            'create_brief_template' => $this->createBriefTemplate($user, $arguments),
            'update_brief_template' => $this->updateBriefTemplate($user, $arguments),
            'delete_brief_template' => $this->deleteBriefTemplate($user, $arguments),
            'list_briefs' => $this->listBriefs($user, $arguments),
            'get_brief' => $this->getBrief($user, $arguments),
            'list_reports' => $this->listReports($user, $arguments),
            'get_report' => $this->getReport($user, $arguments),
            'create_project' => $this->createProject($user, $arguments),
            'get_project' => $this->getProject($user, $arguments),
            'create_project_invite' => $this->createProjectInvite($user, $arguments),
            'update_project' => $this->updateProject($user, $arguments),
            'archive_project' => $this->archiveProject($user, $arguments),
            'add_project_member' => $this->addProjectMember($user, $arguments),
            'update_project_member' => $this->updateProjectMember($user, $arguments),
            'remove_project_member' => $this->removeProjectMember($user, $arguments),
            'update_client' => $this->updateClient($user, $arguments),
            'get_invoice' => $this->getInvoice($user, $arguments),
            'list_users' => $this->listUsers($user, $arguments),
            'get_custom_field_values' => $this->getCustomFieldValues($user, $arguments),
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
            'update_task' => [
                'description' => 'Изменить поля задачи: бюджет, дедлайн, приоритет, исполнителя, название, описание, оценку. Бюджет — денежное поле, действие требует подтверждения. Только администратор.',
                'properties' => [
                    'task_id' => ['type' => 'string', 'description' => 'UUID задачи.'],
                    'title' => ['type' => 'string', 'description' => 'Новое название (необязательно).'],
                    'description' => ['type' => 'string', 'description' => 'Новое описание (необязательно).'],
                    'priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value'), 'description' => 'Новый приоритет (необязательно).'],
                    'deadline' => ['type' => 'string', 'description' => 'Новый дедлайн YYYY-MM-DD; null очищает (необязательно).'],
                    'budget' => ['type' => 'number', 'description' => 'Новый бюджет в рублях; null очищает (необязательно).'],
                    'assignee_id' => ['type' => 'string', 'description' => 'UUID исполнителя; null снимает (необязательно).'],
                    'estimated_minutes' => ['type' => 'integer', 'description' => 'Оценка в минутах (необязательно).'],
                ],
                'required' => ['task_id'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'update_tasks' => [
                'description' => 'Массово изменить любое поле задач: выбрать задачи по task_ids, project_id/ключу, client_id, status или query и передать values; для разных значений по задачам — updates [{task_id, values}]. Меняет title, description/append_description, client_id, project_id, assignee_id, type, priority, status, deadline, budget, estimated_minutes, checklist. Только администратор; требует подтверждения.',
                'properties' => [
                    'task_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'UUID или KEY-12; необязательно, если задан фильтр.'],
                    'project_id' => ['type' => 'string', 'description' => 'UUID или ключ проекта (KOR).'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента.'],
                    'status' => ['type' => 'string', 'enum' => array_column(TaskStatus::cases(), 'value'), 'description' => 'Фильтр по текущему статусу.'],
                    'query' => ['type' => 'string', 'description' => 'Поиск по названию.'],
                    'values' => [
                        'type' => 'object',
                        'description' => 'Поля, которые нужно выставить всем выбранным задачам.',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'append_description' => ['type' => 'string', 'description' => 'Добавить текст в конец описания, сохранив старое.'],
                            'client_id' => ['type' => 'string'],
                            'project_id' => ['type' => 'string'],
                            'assignee_id' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => array_column(TaskType::cases(), 'value')],
                            'priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value')],
                            'status' => ['type' => 'string', 'enum' => array_column(TaskStatus::cases(), 'value')],
                            'deadline' => ['type' => 'string'],
                            'budget' => ['type' => 'number'],
                            'estimated_minutes' => ['type' => 'integer'],
                            'checklist' => ['type' => 'array', 'items' => ['type' => 'object']],
                        ],
                    ],
                    'comment' => ['type' => 'string', 'description' => 'Комментарий к массовой смене статуса (необязательно).'],
                    'updates' => [
                        'type' => 'array',
                        'description' => 'Разные значения по задачам: [{task_id, values}]. Используй для разных ссылок/описаний.',
                        'items' => [
                            'type' => 'object',
                            'required' => ['task_id', 'values'],
                            'properties' => [
                                'task_id' => ['type' => 'string'],
                                'values' => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
                'required' => [],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'distribute_project_budget' => [
                'description' => 'Распределить общую сумму бюджета по задачам одного проекта: equal — поровну (по умолчанию), manual — по allocations. Сумма распределяется точно до копейки. Только администратор; требует подтверждения.',
                'properties' => [
                    'project_id' => ['type' => 'string', 'description' => 'UUID или ключ проекта, например KOR.'],
                    'amount' => ['type' => 'number', 'description' => 'Общая сумма в рублях, например 45500.'],
                    'strategy' => ['type' => 'string', 'enum' => ['equal', 'manual'], 'description' => 'Способ распределения; по умолчанию equal.'],
                    'task_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Необязательно: только эти задачи проекта.'],
                    'allocations' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Для strategy=manual: [{task_id, amount}].'],
                ],
                'required' => ['project_id', 'amount'],
                'roles' => ['admin'],
                'confirm' => true,
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
            'create_project' => [
                'description' => 'Создать проект для клиента. Только администратор.',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'key' => ['type' => 'string'],
                    'client_id' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'site_url' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => array_column(ProjectStatus::cases(), 'value')],
                    'priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value')],
                    'starts_at' => ['type' => 'string'],
                    'due_at' => ['type' => 'string'],
                    'instructions' => ['type' => 'string'],
                ],
                'required' => ['name'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'get_project' => [
                'description' => 'Карточка проекта по UUID или ключу: клиент, участники, приглашения, задачи.',
                'properties' => ['project_id' => ['type' => 'string']],
                'required' => ['project_id'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'create_project_invite' => [
                'description' => 'Одноразовая ссылка-приглашение в проект с ролью и сроком. Только администратор.',
                'properties' => [
                    'project_id' => ['type' => 'string'],
                    'role' => ['type' => 'string', 'enum' => array_column(ProjectMemberRole::cases(), 'value')],
                    'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                    'email' => ['type' => 'string'],
                ],
                'required' => ['project_id', 'role'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'update_client' => [
                'description' => 'Обновить имя, email, телефон или контакты клиента. Только администратор.',
                'properties' => [
                    'client_id' => ['type' => 'string', 'description' => 'UUID или email.'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'phone' => ['type' => 'string'],
                    'messenger_contacts' => ['type' => 'object'],
                ],
                'required' => ['client_id'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'get_invoice' => [
                'description' => 'Счёт по UUID: сумма, статус, клиент, задачи и ссылка.',
                'properties' => ['invoice_id' => ['type' => 'string']],
                'required' => ['invoice_id'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'list_users' => [
                'description' => 'Активные пользователи CRM по роли — для выбора получателя отчёта.',
                'properties' => ['role' => ['type' => 'string', 'enum' => ['admin', 'client']]],
                'required' => [],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'get_custom_field_values' => [
                'description' => 'Значения пользовательских полей клиента, задачи или счёта.',
                'properties' => [
                    'entity_type' => ['type' => 'string', 'enum' => ['client', 'task', 'invoice']],
                    'entity_id' => ['type' => 'string'],
                ],
                'required' => ['entity_type', 'entity_id'],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'update_project' => [
                'description' => 'Обновить проект: название, ключ, клиент, сайт, статус, сроки, инструкции. Только администратор.',
                'properties' => [
                    'project_id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'key' => ['type' => 'string'],
                    'client_id' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'site_url' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => array_column(ProjectStatus::cases(), 'value')],
                    'priority' => ['type' => 'string', 'enum' => array_column(TaskPriority::cases(), 'value')],
                    'starts_at' => ['type' => 'string'],
                    'due_at' => ['type' => 'string'],
                    'instructions' => ['type' => 'string'],
                ],
                'required' => ['project_id', 'name'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'archive_project' => [
                'description' => 'Архивировать проект. Только администратор; требует подтверждения.',
                'properties' => ['project_id' => ['type' => 'string']],
                'required' => ['project_id'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'add_project_member' => [
                'description' => 'Добавить участника в проект с ролью. Только администратор.',
                'properties' => [
                    'project_id' => ['type' => 'string'],
                    'user_id' => ['type' => 'string'],
                    'role' => ['type' => 'string', 'enum' => array_column(ProjectMemberRole::cases(), 'value')],
                ],
                'required' => ['project_id', 'user_id', 'role'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'update_project_member' => [
                'description' => 'Изменить роль участника проекта. Только администратор.',
                'properties' => [
                    'project_id' => ['type' => 'string'],
                    'member_id' => ['type' => 'string'],
                    'role' => ['type' => 'string', 'enum' => array_column(ProjectMemberRole::cases(), 'value')],
                ],
                'required' => ['project_id', 'member_id', 'role'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'remove_project_member' => [
                'description' => 'Убрать участника из проекта. Только администратор.',
                'properties' => [
                    'project_id' => ['type' => 'string'],
                    'member_id' => ['type' => 'string'],
                ],
                'required' => ['project_id', 'member_id'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'create_report' => [
                'description' => 'Создать отчёт клиенту в формате «описание + ссылка»: получатель, задача, повторяемые группы. Только администратор; требует подтверждения.',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Заголовок (необязательно).'],
                    'recipient_id' => ['type' => 'string', 'description' => 'UUID получателя.'],
                    'recipient_email' => ['type' => 'string', 'description' => 'Email получателя.'],
                    'task_id' => ['type' => 'string', 'description' => 'UUID задачи (необязательно).'],
                    'items' => [
                        'type' => 'array',
                        'description' => 'Группы: description + link, опционально скриншот.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => ['type' => 'string'],
                                'link' => ['type' => 'string'],
                                'screenshot_base64' => ['type' => 'string'],
                                'screenshot_mime' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'required' => ['items'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'list_briefs' => [
                'description' => 'Брифы клиентов: статус, прогресс, клиент, шаблон. Клиент видит только свои брифы и брифы своих проектов. Используй для вопросов «какие брифы ждут заполнения», «что нового от клиента».',
                'properties' => [
                    'status' => ['type' => 'string', 'description' => 'open | draft | submitted | archived | all. По умолчанию open.'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента (необязательно).'],
                    'limit' => ['type' => 'integer', 'description' => 'Сколько вернуть, 1–50. По умолчанию 20.'],
                ],
                'required' => [],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'get_brief' => [
                'description' => 'Карточка брифа: шаблон, клиент, прогресс и ответы по секциям. Используй, когда нужно пересказать или проверить ответы клиента.',
                'properties' => [
                    'brief_id' => ['type' => 'string', 'description' => 'UUID брифа.'],
                ],
                'required' => ['brief_id'],
                'roles' => ['admin', 'client'],
                'confirm' => false,
            ],
            'update_brief' => [
                'description' => 'Переименовать бриф: заголовок видят клиент, публичная ссылка и PDF. Используй, когда название пришло из задачи или уточнилось по ходу.',
                'properties' => [
                    'brief_id' => ['type' => 'string', 'description' => 'UUID брифа.'],
                    'title' => ['type' => 'string', 'description' => 'Новый заголовок, до 200 символов.'],
                ],
                'required' => ['brief_id', 'title'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'list_brief_templates' => [
                'description' => 'Каталог шаблонов брифов: slug, название, число секций и полей. Вызывай перед create_brief, чтобы выбрать подходящий шаблон.',
                'properties' => (object) [],
                'required' => [],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'create_brief' => [
                'description' => 'Создать бриф клиента по шаблону и выдать его в кабинет. Можно привязать к задаче (task_id) — клиент и проект возьмутся из неё. Требует подтверждения.',
                'properties' => [
                    'template' => ['type' => 'string', 'description' => 'Slug или UUID шаблона (см. list_brief_templates).'],
                    'task_id' => ['type' => 'string', 'description' => 'UUID задачи, из которой берутся клиент и проект (необязательно).'],
                    'client_id' => ['type' => 'string', 'description' => 'UUID клиента, если он не выводится из задачи.'],
                    'project_id' => ['type' => 'string', 'description' => 'UUID проекта (необязательно).'],
                    'title' => ['type' => 'string', 'description' => 'Название брифа; по умолчанию — название шаблона.'],
                ],
                'required' => ['template'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'research' => [
                'description' => 'Поручить DeepSeek Harness исследование: открыть страницу, собрать свежие факты, сравнить источники. Используй, когда данных нет в CRM или они могли устареть — цены и услуги на сайте, новые разделы, упоминания. Верни ответ пользователю, а не сырой лог. Если вернулся status=running — не опрашивай в этом же ходе: скажи, что исследование идёт, и проверь research_status следующим сообщением.',
                'properties' => [
                    'task' => ['type' => 'string', 'description' => 'Что именно выяснить, одним-двумя предложениями.'],
                    'url' => ['type' => 'string', 'description' => 'Страница-источник (необязательно). Для проекта по умолчанию берётся его site_url.'],
                    'project_id' => ['type' => 'string', 'description' => 'UUID проекта: подставит сайт и контекст проекта.'],
                    'wait_seconds' => ['type' => 'integer', 'description' => 'Сколько ждать ответа, 5–45. По умолчанию 20.'],
                ],
                'required' => ['task'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'research_status' => [
                'description' => 'Проверить фоновое исследование по run_id и забрать ответ, когда оно завершится.',
                'properties' => [
                    'run_id' => ['type' => 'string', 'description' => 'Идентификатор запуска из research.'],
                ],
                'required' => ['run_id'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'create_brief_template' => [
                'description' => 'Создать шаблон брифа: секции и поля. Новый сценарий оформляется схемой, код не нужен. После создания шаблон можно выдать клиенту через create_brief.',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Название шаблона, например «Аудит рекламы».'],
                    'slug' => ['type' => 'string', 'description' => 'Латиница через дефис; по умолчанию из названия.'],
                    'summary' => ['type' => 'string', 'description' => 'Короткое описание для карточки шаблона.'],
                    'icon' => ['type' => 'string', 'description' => 'Имя иконки Lucide, например clipboard-list.'],
                    'estimated_minutes' => ['type' => 'integer', 'description' => 'Сколько минут займёт заполнение.'],
                    'sections' => ['type' => 'array', 'description' => 'Секции: [{"title","description","fields":[{"key","type","label","required","help","options","show_if","validation"}]}]. Типы: text, textarea, number, scale, date, url, email, phone, single_choice, multi_choice, contact_block, file.'],
                ],
                'required' => ['title', 'sections'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'update_brief_template' => [
                'description' => 'Изменить шаблон брифа: название, описание, иконку, время или схему целиком. Версия схемы растёт, уже отправленные брифы сохраняют свой снимок. Действие требует подтверждения.',
                'properties' => [
                    'template' => ['type' => 'string', 'description' => 'UUID или slug шаблона.'],
                    'title' => ['type' => 'string', 'description' => 'Новое название.'],
                    'slug' => ['type' => 'string', 'description' => 'Новый slug.'],
                    'summary' => ['type' => 'string', 'description' => 'Новое описание.'],
                    'icon' => ['type' => 'string', 'description' => 'Новая иконка.'],
                    'estimated_minutes' => ['type' => 'integer', 'description' => 'Новая оценка времени.'],
                    'sections' => ['type' => 'array', 'description' => 'Новая схема целиком; старые ответы не мигрируют.'],
                ],
                'required' => ['template'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'delete_brief_template' => [
                'description' => 'Удалить шаблон брифа. Нельзя удалить шаблон, по которому уже созданы брифы — сначала архивируйте или удалите их. Требует подтверждения.',
                'properties' => [
                    'template' => ['type' => 'string', 'description' => 'UUID или slug шаблона.'],
                ],
                'required' => ['template'],
                'roles' => ['admin'],
                'confirm' => true,
            ],
            'list_reports' => [
                'description' => 'Последние отчёты клиентам с ссылками. Только администратор.',
                'properties' => [
                    'recipient_id' => ['type' => 'string'],
                    'task_id' => ['type' => 'string'],
                    'limit' => ['type' => 'integer'],
                ],
                'required' => [],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'get_report' => [
                'description' => 'Полный отчёт по UUID с группами и ссылками. Только администратор.',
                'properties' => [
                    'report_id' => ['type' => 'string'],
                ],
                'required' => ['report_id'],
                'roles' => ['admin'],
                'confirm' => false,
            ],
            'crm_help' => [
                'description' => 'Найти раздел CRM и инструкцию по вопросу «где/как/что это/куда нажать». Возвращает title, url, summary и шаги. Вызывай для любых вопросов об интерфейсе, прежде чем отвечать по памяти.',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Вопрос или ключевые слова: «счета», «пригласить в проект», «принять работу».'],
                    'page' => ['type' => 'string', 'description' => 'Необязательно: текущая страница (Inertia component или URL).'],
                    'limit' => ['type' => 'integer', 'description' => 'Сколько разделов вернуть, 1–8.'],
                ],
                'required' => ['query'],
                'roles' => ['admin', 'client'],
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
        $query = Task::query()->with(['client', 'assignee']);

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
                'url' => $user->isAdmin()
                    ? route('admin.projects.show', $project)
                    : route('client.projects.show', $project),
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
            ->when(
                Str::isUuid($projectId),
                fn ($query) => $query->whereKey($projectId),
                fn ($query) => $query->where('key', mb_strtoupper($projectId)),
            )
            ->first();

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
            'project_url' => $project !== null
                ? ($user->isAdmin()
                    ? route('admin.projects.show', $project)
                    : route('client.projects.show', $project))
                : null,
            'project' => $project ? [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'color' => $project->color,
                'url' => $user->isAdmin()
                    ? route('admin.projects.show', $project)
                    : route('client.projects.show', $project),
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
            'project_id' => $project?->id,
            'project_key' => $project?->key,
            'project_name' => $project?->name,
            'project_url' => $project !== null
                ? ($user->isAdmin()
                    ? route('admin.projects.show', $project)
                    : route('client.projects.show', $project))
                : null,
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
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateTask(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $task = $this->resolveTask($user, (string) ($arguments['task_id'] ?? ''));

        $data = Validator::make($arguments, [
            'task_id' => ['required', 'uuid', 'exists:tasks,id'],
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'string', 'min:3'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
        ])->validate();

        unset($data['task_id']);

        if ($data === []) {
            throw ValidationException::withMessages(['task' => 'Укажите хотя бы одно поле для изменения.']);
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->sanitizer->sanitize($data['description']);
        }

        $task->update($data);

        return $this->serializer->task($task->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateTasks(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        if (! empty($arguments['updates'])) {
            return $this->updateTasksIndividually($user, $arguments);
        }

        $selectors = Validator::make($arguments, [
            'task_ids' => ['sometimes', 'array', 'max:500'],
            'task_ids.*' => ['string'],
            'project_id' => ['nullable', 'string'],
            'client_id' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'query' => ['nullable', 'string', 'max:200'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $hasSelector = ! empty($selectors['task_ids'])
            || filled($selectors['project_id'] ?? null)
            || filled($selectors['client_id'] ?? null)
            || filled($selectors['status'] ?? null)
            || filled($selectors['query'] ?? null);

        if (! $hasSelector) {
            throw ValidationException::withMessages(['task_ids' => 'Укажите task_ids или хотя бы один фильтр задач.']);
        }

        $values = $arguments['values'] ?? null;
        $data = $this->validateBulkValues(is_array($values) ? $values : [], $user);

        $query = Task::query()->orderBy('number')->orderBy('created_at');

        if (! empty($selectors['task_ids'])) {
            $ids = [];
            foreach ($selectors['task_ids'] as $identifier) {
                $ids[] = $this->resolveTask($user, (string) $identifier)->id;
            }
            $query->whereIn('id', array_values(array_unique($ids)));
        }
        if (filled($selectors['project_id'] ?? null)) {
            $project = $this->resolveProject($user, (string) $selectors['project_id']);
            $query->where('project_id', $project?->id);
        }
        if (filled($selectors['client_id'] ?? null)) {
            $query->where('client_id', $this->resolveClient($user, (string) $selectors['client_id'])->id);
        }
        if (filled($selectors['status'] ?? null)) {
            $query->where('status', $selectors['status']);
        }
        if (filled($selectors['query'] ?? null)) {
            $query->where('title', 'like', '%'.(string) $selectors['query'].'%');
        }

        $tasks = $query->limit(500)->get();
        if ($tasks->isEmpty()) {
            throw ValidationException::withMessages(['task_ids' => 'Не найдено задач для изменения.']);
        }

        $warnings = [];

        DB::transaction(function () use ($tasks, $data, $user, $selectors, &$warnings): void {
            foreach ($tasks as $task) {
                $warning = $this->applyBulkValues($task, $data, $user, $selectors['comment'] ?? null);

                if ($warning !== null) {
                    $warnings[] = $warning;
                }
            }
        });

        $updated = $tasks->map(fn (Task $task): array => $this->serializer->task($task->refresh()))->all();

        return [
            'count' => count($updated),
            'values' => $values,
            'tasks' => $updated,
            'warnings' => $warnings,
            'url' => $tasks->count() === 1 ? route('admin.tasks.show', $tasks->first()) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function validateBulkValues(array $values, User $user): array
    {
        if ($values === []) {
            throw ValidationException::withMessages(['values' => 'Передайте хотя бы одно поле в values.']);
        }

        if (filled($values['project_id'] ?? null) && is_string($values['project_id']) && ! Str::isUuid($values['project_id'])) {
            $values['project_id'] = $this->resolveProject($user, $values['project_id'])?->id;
        }

        if (filled($values['client_id'] ?? null) && is_string($values['client_id'])) {
            $values['client_id'] = $this->resolveClient($user, $values['client_id'])->id;
        }

        $allowed = ['title', 'description', 'append_description', 'client_id', 'project_id', 'assignee_id', 'type', 'priority', 'status', 'deadline', 'budget', 'estimated_minutes', 'checklist'];
        $unknown = array_diff(array_keys($values), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['values' => 'Неизвестные поля: '.implode(', ', $unknown)]);
        }

        return Validator::make($values, [
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'string', 'min:3'],
            'append_description' => ['sometimes', 'string', 'min:1', 'max:5000'],
            'client_id' => ['nullable', 'uuid', 'exists:users,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'deadline' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.text' => ['nullable', 'string', 'max:500'],
            'checklist.*.done' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateTasksIndividually(User $user, array $arguments): array
    {
        $updates = Validator::make($arguments, [
            'updates' => ['required', 'array', 'min:1', 'max:500'],
            'updates.*.task_id' => ['required', 'string'],
            'updates.*.values' => ['required', 'array'],
        ])->validate()['updates'];

        $tasks = [];
        $applied = [];

        foreach ($updates as $update) {
            $task = $this->resolveTask($user, (string) $update['task_id']);
            $values = $this->validateBulkValues((array) $update['values'], $user);
            $tasks[] = $task;
            $applied[] = ['task' => $task, 'data' => $values];
        }

        $warnings = [];

        DB::transaction(function () use ($applied, $user, $arguments, &$warnings): void {
            foreach ($applied as $item) {
                $warning = $this->applyBulkValues($item['task'], $item['data'], $user, $arguments['comment'] ?? null);

                if ($warning !== null) {
                    $warnings[] = $warning;
                }
            }
        });

        $result = collect($tasks)->map(fn (Task $task): array => $this->serializer->task($task->refresh()))->all();
        $keys = [];
        foreach ($updates as $update) {
            $keys = array_merge($keys, array_keys((array) $update['values']));
        }

        return [
            'count' => count($result),
            'values' => array_fill_keys(array_values(array_unique($keys)), true),
            'tasks' => $result,
            'warnings' => $warnings,
            'url' => count($result) === 1 ? route('admin.tasks.show', $tasks[0]) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyBulkValues(Task $task, array $data, User $user, ?string $comment): ?string
    {
        $status = isset($data['status']) ? TaskStatus::from((string) $data['status']) : null;
        unset($data['status']);

        $append = $data['append_description'] ?? null;
        unset($data['append_description']);

        if ($append !== null) {
            $existing = trim((string) $task->description);
            $data['description'] = trim($existing."\n\n".$append);
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->sanitizer->sanitize($data['description']);
        }

        $warning = null;

        if ($status !== null) {
            if (! $this->strictControl->canChange($task, $user, $status)) {
                $warning = ($task->reference() ?? $task->title).': статус не изменён — переход в «'.$status->label().'» недоступен.';
            } else {
                try {
                    $this->changeTaskStatus->execute($task, $status, $user, $comment);
                } catch (ValidationException $exception) {
                    $warning = ($task->reference() ?? $task->title).': '.($exception->errors()['status'][0] ?? 'статус не изменён.');
                }
            }
        }

        if ($data !== []) {
            $task->update($data);
        }

        return $warning;
    }

    private function distributeProjectBudget(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'project_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'strategy' => ['sometimes', Rule::in(['equal', 'manual'])],
            'task_ids' => ['sometimes', 'array', 'max:500'],
            'task_ids.*' => ['uuid', 'distinct', 'exists:tasks,id'],
            'allocations' => ['sometimes', 'array', 'max:500'],
            'allocations.*.task_id' => ['required_with:allocations', 'uuid', 'distinct', 'exists:tasks,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0'],
        ])->validate();

        $project = $this->resolveProject($user, (string) $data['project_id']);
        if ($project === null) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        $query = $project->tasks()->orderBy('number')->orderBy('created_at');
        if (! empty($data['task_ids'])) {
            $query->whereIn('id', $data['task_ids']);
        }
        $tasks = $query->get();

        if ($tasks->isEmpty()) {
            throw ValidationException::withMessages(['project_id' => 'В проекте нет задач для распределения.']);
        }

        $strategy = $data['strategy'] ?? 'equal';
        $totalCents = (int) round((float) $data['amount'] * 100);
        $allocations = $this->allocateBudget($tasks, $totalCents, $strategy, $data['allocations'] ?? []);

        DB::transaction(function () use ($tasks, $allocations): void {
            foreach ($tasks as $task) {
                $task->update(['budget' => number_format($allocations[$task->id] / 100, 2, '.', '')]);
            }
        });

        $result = $tasks->map(fn (Task $task): array => [
            'task_id' => $task->id,
            'reference' => $task->reference(),
            'title' => $task->title,
            'budget' => number_format($allocations[$task->id] / 100, 2, '.', ''),
        ])->all();

        return [
            'project' => ['id' => $project->id, 'key' => $project->key, 'name' => $project->name],
            'amount' => number_format($totalCents / 100, 2, '.', ''),
            'strategy' => $strategy,
            'count' => count($result),
            'allocations' => $result,
            'url' => route('admin.projects.show', $project),
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  array<int, array<string, mixed>>  $manual
     * @return array<string, int> task id => cents
     */
    private function allocateBudget($tasks, int $totalCents, string $strategy, array $manual): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        if ($strategy === 'manual') {
            if ($manual === []) {
                throw ValidationException::withMessages(['allocations' => 'Для strategy=manual передайте allocations.']);
            }

            $allocations = [];
            foreach ($manual as $item) {
                $allocations[(string) $item['task_id']] = (int) round((float) $item['amount'] * 100);
            }

            $taskIds = $tasks->pluck('id')->all();
            if (array_diff(array_keys($allocations), $taskIds) !== [] || array_diff($taskIds, array_keys($allocations)) !== []) {
                throw ValidationException::withMessages(['allocations' => 'Распределение должно покрывать ровно выбранные задачи проекта.']);
            }

            if (array_sum($allocations) !== $totalCents) {
                throw ValidationException::withMessages(['amount' => 'Сумма allocations должна равняться общей сумме.']);
            }

            return $allocations;
        }

        $count = $tasks->count();
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;

        return $tasks->mapWithKeys(fn (Task $task, int $index): array => [$task->id => $base + ($index < $remainder ? 1 : 0)])->all();
    }

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
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createProject(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
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

        $project = $this->projects->create($user, $data);

        return $this->projectPayload($project);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getProject(User $user, array $arguments): array
    {
        $project = $this->resolveProject($user, (string) ($arguments['project_id'] ?? ''));
        if ($project === null) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        $project->load(['client:id,name,email', 'members.user:id,name,email', 'invites.creator:id,name']);

        return [
            ...$this->projectPayload($project),
            'members' => $project->members->map(fn ($member): array => [
                'id' => $member->id,
                'user' => $member->user?->only(['id', 'name', 'email']),
                'role' => $member->role->value,
            ])->all(),
            'invites' => $project->invites->map(fn ($invite): array => [
                'id' => $invite->id,
                'role' => $invite->role->value,
                'email' => $invite->email,
                'status' => $invite->status(),
                'expires_at' => $invite->expires_at->toIso8601String(),
            ])->all(),
            'tasks_count' => $project->tasks()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createProjectInvite(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $data = Validator::make($arguments, [
            'project_id' => ['required', 'string'],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
            'days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ])->validate();

        $project = $this->resolveProject($user, (string) $data['project_id']);
        if ($project === null) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        $result = $this->invites->create($project, $user, [
            'role' => $data['role'],
            'days' => $data['days'] ?? 7,
            'email' => $data['email'] ?? null,
        ]);

        return [
            'id' => $result['invite']->id,
            'url' => $result['url'],
            'role' => $result['invite']->role->value,
            'expires_at' => $result['invite']->expires_at->toIso8601String(),
            'note' => 'Ссылка одноразовая; показывается только сейчас.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateClient(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $client = $this->resolveClient($user, (string) ($arguments['client_id'] ?? ''));
        $data = Validator::make($arguments, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$client->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'messenger_contacts' => ['nullable', 'array'],
        ])->validate();

        unset($data['client_id']);
        $client->update($data);

        return ['client' => $client->only(['id', 'name', 'email', 'phone', 'messenger_contacts'])];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getInvoice(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $invoice = Invoice::query()->with(['client:id,name,email', 'task:id,title'])->findOrFail((string) ($arguments['invoice_id'] ?? ''));

        return [
            ...$this->serializer->invoice($invoice),
            'url' => route('admin.invoices.show', $invoice),
            'client_url' => route('client.invoices.show', $invoice),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function listUsers(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $query = User::query()->where('is_active', true)->orderBy('name');
        if (filled($arguments['role'] ?? null)) {
            $query->where('role', $arguments['role']);
        }

        return ['users' => $query->limit(100)->get(['id', 'name', 'email', 'role'])->map(fn (User $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'email' => $item->email,
            'role' => $item->role->value,
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getCustomFieldValues(User $user, array $arguments): array
    {
        $entity = CustomFieldEntity::tryFrom((string) ($arguments['entity_type'] ?? ''));
        if ($entity === null) {
            throw ValidationException::withMessages(['entity_type' => 'Неизвестная сущность.']);
        }

        $modelClass = $entity->modelClass();
        $model = $modelClass::query()->find((string) ($arguments['entity_id'] ?? ''));
        if ($model === null) {
            throw ValidationException::withMessages(['entity_id' => 'Запись не найдена.']);
        }

        if ($user->isClient()) {
            $ownerId = $entity === CustomFieldEntity::Client
                ? $model->getKey()
                : ($model->client_id ?? null);

            if ($ownerId !== $user->id) {
                throw ValidationException::withMessages(['entity_id' => 'Запись недоступна.']);
            }
        }

        return [
            'entity_type' => $entity->value,
            'entity_id' => $model->getKey(),
            'fields' => $this->customFields->valuesFor($model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'key' => $project->key,
            'name' => $project->name,
            'status' => $project->status->value,
            'priority' => $project->priority->value,
            'client' => $project->client?->only(['id', 'name']),
            'site_url' => $project->site_url,
            'url' => route('admin.projects.show', $project),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateProject(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $project = $this->resolveProject($user, (string) ($arguments['project_id'] ?? ''));
        if ($project === null) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

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

        $updated = $this->projects->update($project, $data);

        return $this->projectPayload($updated);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function archiveProject(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $project = $this->resolveProject($user, (string) ($arguments['project_id'] ?? ''));
        if ($project === null) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        $this->projects->archive($project);

        return [...$this->projectPayload($project->refresh()), 'archived' => true];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function addProjectMember(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $project = $this->resolveProject($user, (string) ($arguments['project_id'] ?? ''));
        $data = Validator::make($arguments, [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ])->validate();
        $member = $project->members()->updateOrCreate(
            ['user_id' => $data['user_id']],
            ['role' => $data['role'], 'added_by' => $user->id],
        );

        return ['member' => $this->memberPayload($member->load('user:id,name,email'))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateProjectMember(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $project = $this->resolveProject($user, (string) ($arguments['project_id'] ?? ''));
        $data = Validator::make($arguments, [
            'member_id' => ['required', 'uuid'],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ])->validate();
        $member = ProjectMember::query()->where('project_id', $project->id)->findOrFail($data['member_id']);
        $member->update(['role' => $data['role']]);

        return ['member' => $this->memberPayload($member->load('user:id,name,email'))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function removeProjectMember(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $project = $this->resolveProject($user, (string) ($arguments['project_id'] ?? ''));
        $member = ProjectMember::query()->where('project_id', $project->id)->findOrFail((string) ($arguments['member_id'] ?? ''));
        $member->delete();

        return ['removed' => true, 'member_id' => $member->id];
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

    private function createReport(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $items = is_array($arguments['items'] ?? null) ? $arguments['items'] : [];
        $items = array_map(fn (array $item): array => [
            'summary' => $item['description'] ?? $item['summary'] ?? '',
            'test_url' => $item['link'] ?? $item['test_url'] ?? null,
            'screenshot_base64' => $item['screenshot_base64'] ?? null,
            'screenshot_mime' => $item['screenshot_mime'] ?? null,
            'screenshot_name' => $item['screenshot_name'] ?? null,
        ], $items);

        $report = $this->reports->create($user, [
            'title' => $arguments['title'] ?? null,
            'recipient_id' => $arguments['recipient_id'] ?? null,
            'recipient_email' => $arguments['recipient_email'] ?? null,
            'task_id' => $arguments['task_id'] ?? null,
            'items' => $items,
        ]);

        return [
            'id' => $report->id,
            'title' => $report->title,
            'url' => route('reports.show', $report),
            'recipient' => $report->recipient?->only(['id', 'name', 'email']),
            'task' => $report->task?->only(['id', 'title']),
            'items_count' => $report->items->count(),
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
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    /**
     * Запускает исследование в DeepSeek Harness и ждёт ответ в пределах
     * `wait_seconds`. Долгие прогоны возвращают run_id для research_status.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function research(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'task' => ['required', 'string', 'min:8', 'max:2000'],
            'url' => ['nullable', 'url', 'max:500'],
            'project_id' => ['nullable', 'uuid'],
            'wait_seconds' => ['nullable', 'integer', 'min:1', 'max:45'],
        ])->validate();

        $project = null;

        if (filled($data['project_id'] ?? null)) {
            $project = Project::query()->find((string) $data['project_id']);

            if ($project === null) {
                throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
            }
        }

        $url = $data['url'] ?? null;

        if ($url === null && $project !== null) {
            $site = trim((string) $project->site_url);
            $url = $site !== '' ? $site : null;
        }

        $task = (string) $data['task'];

        if ($project !== null) {
            // У ядра свой потолок в 2000 символов; контекст проекта ужимаем
            // так, чтобы сама задача осталась целиком.
            $budget = max(200, 1900 - mb_strlen($task));
            $context = Str::limit(collect([
                $project->name,
                $project->site_url,
                trim((string) $project->agents_md),
            ])->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')->implode("\n"), $budget, '…');

            $task = "Проект: {$context}\n\nЗадача: {$task}";
        }

        $started = $this->kernel->startResearch($task, $url);
        $runId = (string) ($started['run_id'] ?? '');

        if ($runId === '') {
            throw ValidationException::withMessages(['task' => 'Исследовательский сервис не вернул run_id.']);
        }

        $wait = (int) ($data['wait_seconds'] ?? 20);
        $deadline = microtime(true) + $wait;
        $payload = ['status' => (string) ($started['status'] ?? 'running'), 'run_id' => $runId];
        $status = $payload['status'];

        $pollMs = max(50, (int) config('ai.research_poll_ms', 2000));

        while (in_array($status, ['running', 'queued'], true) && microtime(true) < $deadline) {
            usleep($pollMs * 1000);
            $payload = $this->kernel->researchStatus($runId);
            $status = (string) ($payload['status'] ?? 'running');
        }

        $answer = $payload['answer'] ?? null;

        if ($status === 'completed' && is_string($answer) && trim($answer) !== '') {
            return [
                'status' => 'completed',
                'run_id' => $runId,
                'answer' => trim($answer),
                'url' => $url,
                'project' => $project?->key,
            ];
        }

        if ($status === 'failed') {
            return [
                'status' => 'failed',
                'run_id' => $runId,
                'error' => $payload['error'] ?? 'Исследование не удалось.',
                'url' => $url,
            ];
        }

        return [
            'status' => $status,
            'run_id' => $runId,
            'url' => $url,
            'message' => 'Исследование ещё идёт. Проверь статус через research_status и вернись с ответом.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function researchStatus(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $runId = trim((string) ($arguments['run_id'] ?? ''));

        if ($runId === '' || strlen($runId) > 120) {
            throw ValidationException::withMessages(['run_id' => 'Нужен run_id из research.']);
        }

        return $this->kernel->researchStatus($runId);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateBrief(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'brief_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
        ])->validate();

        $brief = Brief::query()->visibleTo($user)->find((string) $data['brief_id']);

        if ($brief === null) {
            throw ValidationException::withMessages(['brief_id' => 'Бриф не найден.']);
        }

        $brief = $this->briefService->rename($brief, (string) $data['title']);

        return [
            'id' => $brief->id,
            'title' => $brief->title,
            'status' => $brief->status?->value,
            'status_label' => $brief->status?->label(),
            'url' => route('admin.briefs.show', $brief),
        ];
    }

    private function listBriefTemplates(User $user): array
    {
        $this->assertAdmin($user);

        return ['templates' => BriefTemplate::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (BriefTemplate $template): array => [
                ...$template->toLibraryArray(),
                'used_in_briefs' => $template->briefs()->count(),
            ])->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createBrief(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'template' => ['required', 'string', 'max:120'],
            'task_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'project_id' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:200'],
        ])->validate();

        $identifier = (string) $data['template'];
        $template = BriefTemplate::query()
            ->where(fn ($query) => $query
                ->where('slug', $identifier)
                ->when(Str::isUuid($identifier), fn ($builder) => $builder->orWhere('id', $identifier)))
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages(['template' => 'Шаблон брифа не найден или выключен. Посмотрите list_brief_templates.']);
        }

        $clientId = $data['client_id'] ?? null;
        $projectId = $data['project_id'] ?? null;

        if (filled($data['task_id'] ?? null)) {
            $task = Task::query()->with('project:id,key,name,client_id')->find((string) $data['task_id']);

            if ($task === null) {
                throw ValidationException::withMessages(['task_id' => 'Задача не найдена.']);
            }

            $projectId ??= $task->project_id;
            $clientId ??= $task->client_id;
        }

        if ($clientId !== null && ! User::query()->whereKey($clientId)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Клиент не найден.']);
        }

        if ($projectId !== null && ! Project::query()->whereKey($projectId)->exists()) {
            throw ValidationException::withMessages(['project_id' => 'Проект не найден.']);
        }

        $brief = $this->briefService->create($template, [
            'title' => $data['title'] ?? null,
            'client_id' => $clientId,
            'project_id' => $projectId,
        ], $user);

        return [
            'id' => $brief->id,
            'title' => $brief->title,
            'status' => $brief->status?->value,
            'status_label' => $brief->status?->label(),
            'template' => $template->title,
            'client' => $brief->client?->name,
            'project' => $brief->project?->key,
            'url' => route('admin.briefs.show', $brief),
        ];
    }

    private function createBriefTemplate(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $data = Validator::make($arguments, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'summary' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:64'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sections' => ['required', 'array', 'min:1', 'max:30'],
        ])->validate();

        $schema = $this->prepareBriefSchema($data);

        $template = BriefTemplate::query()->create([
            'slug' => $this->uniqueBriefSlug($data['slug'] ?? $data['title']),
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'icon' => $data['icon'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'schema' => $schema,
            'schema_hash' => BriefSchema::hash($schema),
            'version' => 1,
            'is_active' => true,
            'sort_order' => 100,
        ]);

        return $this->briefTemplatePayload($template);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateBriefTemplate(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $template = $this->findBriefTemplate((string) ($arguments['template'] ?? ''));

        $data = Validator::make($arguments, [
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'summary' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:64'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sections' => ['nullable', 'array', 'min:1', 'max:30'],
        ])->validate();

        if (filled($data['slug'] ?? null) && $data['slug'] !== $template->slug) {
            $taken = BriefTemplate::query()->where('slug', $data['slug'])->whereKeyNot($template->id)->exists();

            if ($taken) {
                throw ValidationException::withMessages(['slug' => 'Такой slug уже занят другим шаблоном.']);
            }
        }

        $attributes = [
            'title' => $data['title'] ?? $template->title,
            'slug' => $data['slug'] ?? $template->slug,
            'summary' => $data['summary'] ?? $template->summary,
            'icon' => $data['icon'] ?? $template->icon,
            'estimated_minutes' => $data['estimated_minutes'] ?? $template->estimated_minutes,
        ];

        if (filled($data['sections'] ?? null)) {
            $schema = $this->prepareBriefSchema([
                'title' => $attributes['title'],
                'sections' => $data['sections'],
                'slug' => $attributes['slug'],
                'summary' => $attributes['summary'],
                'icon' => $attributes['icon'],
                'estimated_minutes' => $attributes['estimated_minutes'],
            ]);

            $attributes['schema'] = $schema;
            $attributes['schema_hash'] = BriefSchema::hash($schema);
            $attributes['version'] = $template->version + 1;
        }

        $template->update($attributes);

        return $this->briefTemplatePayload($template->refresh());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteBriefTemplate(User $user, array $arguments): array
    {
        $this->assertAdmin($user);

        $template = $this->findBriefTemplate((string) ($arguments['template'] ?? ''));
        $used = $template->briefs()->count();

        if ($used > 0) {
            throw ValidationException::withMessages([
                'template' => "Шаблон используют {$used} бриф(ов). Удалить нельзя: отправленные брифы хранят ссылку на него.",
            ]);
        }

        $payload = $this->briefTemplatePayload($template);
        $template->delete();

        return [...$payload, 'deleted' => true];
    }

    /**
     * Достраивает ключи секциям и полям и проверяет схему целиком.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareBriefSchema(array $data): array
    {
        $sections = [];
        $seenSections = [];

        foreach ((array) ($data['sections'] ?? []) as $sectionIndex => $section) {
            if (! is_array($section)) {
                continue;
            }

            $title = trim((string) ($section['title'] ?? ''));
            $key = trim((string) ($section['key'] ?? '')) ?: Str::slug($title) ?: 'section-'.($sectionIndex + 1);
            $key = $this->uniqueKey($key, $seenSections);
            $seenSections[] = $key;

            $fields = [];
            $seenFields = [];

            foreach ((array) ($section['fields'] ?? []) as $fieldIndex => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $label = trim((string) ($field['label'] ?? ''));
                $fieldKey = trim((string) ($field['key'] ?? '')) ?: Str::slug($label) ?: 'field-'.($fieldIndex + 1);
                $fieldKey = $this->uniqueKey($fieldKey, $seenFields);
                $seenFields[] = $fieldKey;

                $fields[] = [
                    ...$field,
                    'key' => $fieldKey,
                    'label' => $label !== '' ? $label : $fieldKey,
                    'type' => (string) ($field['type'] ?? 'text'),
                ];
            }

            $sections[] = [
                ...$section,
                'key' => $key,
                'title' => $title !== '' ? $title : $key,
                'fields' => $fields,
            ];
        }

        $schema = BriefSchema::normalize([
            'slug' => (string) ($data['slug'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['summary'] ?? ''),
            'icon' => (string) ($data['icon'] ?? ''),
            'estimated_minutes' => (int) ($data['estimated_minutes'] ?? 0),
            'sections' => $sections,
        ]);

        $errors = BriefSchema::validateDefinition($schema);

        if ($errors !== []) {
            throw ValidationException::withMessages(['sections' => $errors]);
        }

        return $schema;
    }

    /**
     * @param  list<string>  $seen
     */
    private function uniqueKey(string $key, array $seen): string
    {
        $base = $key;
        $suffix = 2;

        while (in_array($key, $seen, true)) {
            $key = $base.'-'.$suffix;
            $suffix++;
        }

        return $key;
    }

    private function uniqueBriefSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'brief';
        $slug = $base;
        $suffix = 2;

        while (BriefTemplate::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function findBriefTemplate(string $value): BriefTemplate
    {
        $template = BriefTemplate::query()
            ->where(fn ($query) => $query
                ->where('slug', $value)
                ->when(Str::isUuid($value), fn ($builder) => $builder->orWhere('id', $value)))
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages(['template' => 'Шаблон брифа не найден.']);
        }

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function briefTemplatePayload(BriefTemplate $template): array
    {
        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'title' => $template->title,
            'summary' => $template->summary,
            'version' => $template->version,
            'sections' => count($template->sections()),
            'fields' => $template->fieldCount(),
            'is_active' => $template->is_active,
            'url' => route('admin.briefs.templates'),
        ];
    }

    private function listBriefs(User $user, array $arguments): array
    {
        $status = (string) ($arguments['status'] ?? 'open');
        $query = Brief::query()
            ->visibleTo($user)
            ->with(['template:id,title,slug', 'client:id,name', 'project:id,key,name']);

        match ($status) {
            'draft' => $query->whereIn('status', [BriefStatus::Draft, BriefStatus::InProgress]),
            'submitted' => $query->where('status', BriefStatus::Submitted),
            'archived' => $query->where('status', BriefStatus::Archived),
            'all' => null,
            default => $query->whereIn('status', BriefStatus::openCases()),
        };

        if (filled($arguments['client_id'] ?? null)) {
            $this->assertAdmin($user);

            if (! Str::isUuid((string) $arguments['client_id'])) {
                throw ValidationException::withMessages(['client_id' => 'client_id должен быть UUID.']);
            }

            $query->where('client_id', $arguments['client_id']);
        }

        return ['briefs' => $query->orderByDesc('updated_at')
            ->limit(min(max((int) ($arguments['limit'] ?? 20), 1), 50))
            ->get()
            ->map(static fn (Brief $brief): array => [
                'id' => $brief->id,
                'title' => $brief->title,
                'status' => $brief->status?->value,
                'status_label' => $brief->status?->label(),
                'progress' => $brief->progress,
                'template' => $brief->template?->title,
                'client' => $brief->client?->name,
                'project' => $brief->project?->key,
                'updated_at' => $brief->updated_at?->toIso8601String(),
            ])->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getBrief(User $user, array $arguments): array
    {
        $briefId = (string) ($arguments['brief_id'] ?? '');

        $brief = Str::isUuid($briefId)
            ? Brief::query()
                ->visibleTo($user)
                ->with(['template:id,title,slug,schema', 'client:id,name', 'project:id,key,name'])
                ->find($briefId)
            : null;

        if ($brief === null) {
            throw ValidationException::withMessages(['brief_id' => 'Бриф не найден.']);
        }

        return [
            'id' => $brief->id,
            'title' => $brief->title,
            'status' => $brief->status?->value,
            'status_label' => $brief->status?->label(),
            'progress' => $brief->progress,
            'template' => $brief->template?->title,
            'client' => $brief->client?->name,
            'project' => $brief->project?->key,
            'submitted_at' => $brief->submitted_at?->toIso8601String(),
            'url' => route('admin.briefs.show', $brief),
            'sections' => $this->briefs->sections($brief),
        ];
    }

    private function listReports(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $query = TaskReport::query()->with(['recipient:id,name,email', 'task:id,title'])->latest();
        if (filled($arguments['recipient_id'] ?? null)) {
            $query->where('recipient_id', $arguments['recipient_id']);
        }
        if (filled($arguments['task_id'] ?? null)) {
            $query->where('task_id', $arguments['task_id']);
        }

        return ['reports' => $query->limit(min(max((int) ($arguments['limit'] ?? 20), 1), 100))->get()->map(fn (TaskReport $report): array => [
            'id' => $report->id,
            'title' => $report->title,
            'display_title' => $report->title ?: 'Отчёт от '.$report->sent_at?->format('d.m.Y'),
            'recipient' => $report->recipient?->only(['id', 'name', 'email']),
            'task' => $report->task?->only(['id', 'title']),
            'url' => route('reports.show', $report),
            'sent_at' => $report->sent_at?->toIso8601String(),
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getReport(User $user, array $arguments): array
    {
        $this->assertAdmin($user);
        $report = TaskReport::query()->with(['items', 'recipient:id,name,email', 'task:id,title'])->findOrFail((string) ($arguments['report_id'] ?? ''));

        return [
            'id' => $report->id,
            'title' => $report->title,
            'url' => route('reports.show', $report),
            'recipient' => $report->recipient?->only(['id', 'name', 'email']),
            'task' => $report->task?->only(['id', 'title']),
            'items' => $report->items->map(fn (TaskReportItem $item): array => [
                'description' => $item->content,
                'link' => $item->test_url,
                'screenshot' => $item->screenshot_id !== null,
            ])->all(),
        ];
    }

    private function crmHelp(User $user, array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $query = $query !== '' ? $query : 'помощь по CRM';
        $limit = (int) ($arguments['limit'] ?? 5);

        return [
            'version' => CrmHelpService::VERSION,
            'query' => $query,
            'results' => $this->help->search(
                $user,
                $query,
                isset($arguments['page']) ? (string) $arguments['page'] : null,
                $limit,
            ),
        ];
    }

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
        $query = Task::query()->when($user->isClient(), fn ($query) => $query->visibleTo($user));

        if (Str::isUuid($id)) {
            $task = $query->find($id);
        } elseif (preg_match('/^([a-z0-9]+)-(\d+)$/i', $id, $matches) === 1) {
            $task = $query
                ->whereHas('project', fn ($q) => $q->where('key', mb_strtoupper($matches[1])))
                ->where('number', (int) $matches[2])
                ->first();
        } else {
            $task = null;
        }

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
