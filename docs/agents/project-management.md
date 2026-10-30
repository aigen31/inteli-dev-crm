# Шаблоны задач и WYSIWYG

## Зачем

Самозанятый выигрывает не от «ещё одной канбан-доски», а от повторяемости: одна и та же услуга должна оформляться одинаково понятно. Шаблон заменяет абстрактное «сделайте красиво» структурой: что входит, что нужно от клиента, критерии готовности и чек-лист.

## Модель

`task_templates`:

- `name`, `summary`, `description` (HTML, санитайзится);
- `checklist` — JSON-массив `{text, done}`;
- `default_type`, `default_priority`, `default_status`;
- `default_assignee_id`, `default_due_days` — **необязательны** (позиционирование для самозанятого);
- `estimated_minutes`, `client_id` (общий или личный шаблон), `is_active`, `is_shared`, `usage_count`;
- soft deletes, `created_by`.

`tasks` получил `task_template_id`, `assignee_id`, `checklist`, `source` (`manual|template|client|ai|api`), `estimated_minutes`.

## Поток

1. Админ создаёт шаблон в `/admin/task-templates/create` или генерирует черновик через Кики.
2. В редакторе: TipTap-описание + конструктор чек-листа + дефолты; справа живой предпросмотр.
3. Применение: `/admin/task-templates` → «Применить» → клиент/название → `POST /admin/task-templates/{id}/apply`.
4. `TaskTemplateService::apply()` создаёт задачу через `CreateTaskAction`, подставляет описание, чек-лист, срок (если задан `default_due_days`), исполнителя (если задан), ставит `source=template` и инкрементит `usage_count`.
5. Клиент может выбрать шаблон в `/tasks/create`; создаётся заявка `pending_review`, `source=client`.

## WYSIWYG и безопасность

- `Components/Form/RichTextEditor.vue` — TipTap; StarterKit уже включает Link/Underline.
- Сервер: `HtmlSanitizer` (allowlist тегов/атрибутов, запрет `script/style/iframe`, проверка схем ссылок).
- Сохраняйте только очищенный HTML. На клиенте `v-html` допустим только для данных, прошедших серверный санитайзер.
- Простые текстовые описания тоже безопасны: санитайзер возвращает текст без тегов.

## ИИ-генерация

`POST /admin/task-templates/generate` → `TemplateGenerator`:

- при настроенном OpenAI-совместимом провайдере просит JSON `{name, summary, description, checklist, default_priority, default_due_days}`;
- без ключа/при ошибке возвращает эвристический fallback (`source: fallback`, `warning`);
- ответ — черновик, пользователь редактирует и сохраняет.

## API и MCP

- `GET/POST/PUT /api/v1/task-templates`, `POST /api/v1/task-templates/{id}/apply`.
- MCP: `list_task_templates`, `apply_task_template`.
- Кики: `list_task_templates`, `apply_task_template`, `create_task_template`.

## Где смотреть

- `app/Models/TaskTemplate.php`, `app/Services/TaskTemplateService.php`, `app/Http/Controllers/Admin/TaskTemplateController.php`.
- Страницы: `Admin/TaskTemplates/Index.vue`, `Admin/TaskTemplates/Edit.vue`, `Client/Tasks/Create.vue`.
- Сид: `database/seeders/ProjectManagementSeeder.php`.
