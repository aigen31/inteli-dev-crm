import { randomBytes } from 'node:crypto';

const str = (description) => ({ type: 'string', description });
const int = (description, minimum = 1, maximum = 100) => ({ type: 'integer', description, minimum, maximum });

const READ = { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false };
const WRITE = { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false };
const DESTRUCTIVE = { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false };

function tool(name, description, properties, required, annotations, handler) {
    return {
        name,
        description,
        inputSchema: {
            type: 'object',
            properties,
            required,
            additionalProperties: false,
        },
        annotations,
        handler,
    };
}

export function buildTools(api) {
    return [
        tool(
            'crm_overview',
            'Сводка CRM: клиенты, активные задачи, просрочки, счета, доход за месяц. Начните с этого инструмента.',
            {},
            [],
            READ,
            () => api.get('/meta/overview'),
        ),
        tool(
            'list_clients',
            'Найти клиентов по имени, email или телефону. Для клиентского токена вернёт только его запись.',
            {
                query: str('Поисковая строка'),
                active: { type: 'boolean', description: 'Только активные или только деактивированные' },
                per_page: int('Сколько вернуть', 1, 100),
            },
            [],
            READ,
            (args) => api.get('/clients', { q: args.query, active: args.active, per_page: args.per_page }),
        ),
        tool(
            'get_client',
            'Карточка клиента: контакты, пользовательские поля, количество задач и счетов.',
            { client_id: str('UUID клиента') },
            ['client_id'],
            READ,
            (args) => api.get(`/clients/${args.client_id}`),
        ),
        tool(
            'create_client',
            'Создать клиентский аккаунт. Если пароль не передан, будет сгенерирован временный.',
            {
                name: str('Имя клиента'),
                email: str('Email клиента'),
                phone: str('Телефон'),
                password: str('Пароль, минимум 8 символов'),
            },
            ['name', 'email'],
            WRITE,
            async (args) => {
                const password = args.password || randomBytes(9).toString('base64url');
                const result = await api.post('/clients', { ...args, password });
                return { ...result, temporary_password: args.password ? undefined : password };
            },
        ),
        tool(
            'update_client',
            'Обновить имя, email, телефон или активность клиента.',
            {
                client_id: str('UUID клиента'),
                name: str('Имя'),
                email: str('Email'),
                phone: str('Телефон'),
                is_active: { type: 'boolean', description: 'Активен ли аккаунт' },
            },
            ['client_id'],
            WRITE,
            (args) => {
                const { client_id, ...body } = args;
                return api.put(`/clients/${client_id}`, body);
            },
        ),
        tool(
            'list_tasks',
            'Список задач с фильтрами. Клиентский токен видит только свои задачи.',
            {
                status: {
                    type: 'string',
                    enum: ['pending_review', 'queued', 'in_progress', 'awaiting_review', 'rejected_by_client', 'completed', 'rejected_by_admin'],
                    description: 'Статус',
                },
                client_id: str('UUID клиента (только для админа)'),
                query: str('Поиск по названию'),
                per_page: int('Сколько вернуть', 1, 100),
            },
            [],
            READ,
            (args) => api.get('/tasks', { status: args.status, client_id: args.client_id, q: args.query, per_page: args.per_page }),
        ),
        tool(
            'get_task',
            'Подробности задачи: описание, чек-лист, статус, клиент, исполнитель, поля.',
            { task_id: str('UUID задачи') },
            ['task_id'],
            READ,
            (args) => api.get(`/tasks/${args.task_id}`),
        ),
        tool(
            'create_task',
            'Создать задачу. Для админа обязателен client_id; клиент создаёт заявку себе.',
            {
                title: str('Название задачи'),
                description: str('Описание, можно HTML'),
                client_id: str('UUID клиента (для админа)'),
                template_id: str('UUID шаблона, чтобы применить структуру'),
                priority: { type: 'string', enum: ['low', 'medium', 'high'] },
                deadline: str('YYYY-MM-DD'),
                assignee_id: str('UUID исполнителя'),
                checklist: {
                    type: 'array',
                    items: { type: 'object', properties: { text: str('Шаг'), done: { type: 'boolean' } }, required: ['text'] },
                },
                custom_fields: { type: 'object', additionalProperties: true, description: 'Значения пользовательских полей по ключам' },
            },
            ['title'],
            WRITE,
            (args) => api.post('/tasks', args),
        ),
        tool(
            'update_task_status',
            'Перевести задачу в другой статус. Только администратор.',
            {
                task_id: str('UUID задачи'),
                status: {
                    type: 'string',
                    enum: ['pending_review', 'queued', 'in_progress', 'awaiting_review', 'rejected_by_client', 'completed', 'rejected_by_admin'],
                },
                comment: str('Комментарий к переходу'),
            },
            ['task_id', 'status'],
            WRITE,
            (args) => api.patch(`/tasks/${args.task_id}/status`, { status: args.status, comment: args.comment }),
        ),
        tool(
            'list_task_templates',
            'Доступные шаблоны задач. Клиент видит только опубликованные общие шаблоны.',
            { per_page: int('Сколько вернуть', 1, 100) },
            [],
            READ,
            (args) => api.get('/task-templates', { per_page: args.per_page }),
        ),
        tool(
            'apply_task_template',
            'Создать задачу из шаблона для клиента. Только администратор.',
            {
                template_id: str('UUID шаблона'),
                client_id: str('UUID клиента'),
                title: str('Переопределить название'),
                deadline: str('YYYY-MM-DD'),
                assignee_id: str('UUID исполнителя'),
            },
            ['template_id', 'client_id'],
            WRITE,
            (args) => {
                const { template_id, ...body } = args;
                return api.post(`/task-templates/${template_id}/apply`, body);
            },
        ),
        tool(
            'create_task_template',
            'Создать шаблон задачи с описанием и чек-листом. Только администратор.',
            {
                name: str('Название шаблона'),
                summary: str('Короткое описание'),
                description: str('HTML-описание, что входит и критерии готовности'),
                checklist: { type: 'array', items: { type: 'string' }, description: 'Пункты чек-листа' },
                default_priority: { type: 'string', enum: ['low', 'medium', 'high'] },
                default_due_days: int('Срок по умолчанию в днях', 1, 365),
            },
            ['name'],
            WRITE,
            (args) => api.post('/task-templates', {
                ...args,
                default_type: 'one_time',
                default_priority: args.default_priority || 'medium',
                default_status: 'pending_review',
            }),
        ),
        tool(
            'list_invoices',
            'Список счетов. Клиентский токен видит только свои счета.',
            {
                status: { type: 'string', enum: ['pending', 'paid', 'cancelled', 'refunded'] },
                client_id: str('UUID клиента (только для админа)'),
                per_page: int('Сколько вернуть', 1, 100),
            },
            [],
            READ,
            (args) => api.get('/invoices', { status: args.status, client_id: args.client_id, per_page: args.per_page }),
        ),
        tool(
            'get_invoice',
            'Детали счёта: сумма, статус, способ оплаты, задача, клиент, поля.',
            { invoice_id: str('UUID счёта') },
            ['invoice_id'],
            READ,
            (args) => api.get(`/invoices/${args.invoice_id}`),
        ),
        tool(
            'create_invoice',
            'Создать счёт по задаче. Денежное действие — подтверждайте осознанно.',
            {
                task_id: str('UUID задачи'),
                amount: { type: 'number', description: 'Сумма в рублях', minimum: 0.01 },
                description: str('Назначение счёта'),
                payment_method: { type: 'string', enum: ['yookassa', 'transfer'], description: 'Способ оплаты' },
            },
            ['task_id', 'amount', 'description', 'payment_method'],
            DESTRUCTIVE,
            (args) => api.post('/invoices', args),
        ),
        tool(
            'mark_invoice_paid',
            'Подтвердить прямой перевод и отметить счёт оплаченным. Только админ, денежное действие.',
            { invoice_id: str('UUID счёта') },
            ['invoice_id'],
            DESTRUCTIVE,
            (args) => api.patch(`/invoices/${args.invoice_id}/mark-paid`),
        ),
        tool(
            'list_custom_fields',
            'Определения пользовательских полей: ключи, типы, варианты, обязательность.',
            {
                entity_type: { type: 'string', enum: ['client', 'task', 'invoice'] },
            },
            [],
            READ,
            (args) => api.get('/custom-fields', { entity_type: args.entity_type }),
        ),
        tool(
            'get_custom_field_values',
            'Значения пользовательских полей у клиента, задачи или счёта.',
            {
                entity_type: { type: 'string', enum: ['client', 'task', 'invoice'] },
                entity_id: str('UUID сущности'),
            },
            ['entity_type', 'entity_id'],
            READ,
            (args) => api.get(`/custom-fields/${args.entity_type}/${args.entity_id}/values`),
        ),
        tool(
            'set_custom_field_values',
            'Заполнить пользовательские поля у клиента, задачи или счёта. Только админ.',
            {
                entity_type: { type: 'string', enum: ['client', 'task', 'invoice'] },
                entity_id: str('UUID сущности'),
                fields: { type: 'object', additionalProperties: true, description: 'Объект key → value' },
            },
            ['entity_type', 'entity_id', 'fields'],
            WRITE,
            (args) => api.put(`/custom-fields/${args.entity_type}/${args.entity_id}/values`, { fields: args.fields }),
        ),
        tool(
            'ask_assistant',
            'Задать вопрос Кики — встроенному ассистенту CRM. Он учитывает роль токена и умеет выполнять действия.',
            {
                message: str('Вопрос или задача'),
                conversation_id: str('UUID диалога для продолжения'),
            },
            ['message'],
            WRITE,
            (args) => api.post('/assistant/chat', { message: args.message, conversation_id: args.conversation_id }),
        ),
    ];
}

export function toolByName(tools, name) {
    return tools.find((item) => item.name === name) || null;
}
