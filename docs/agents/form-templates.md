# Формы заявок (structured intake)

## Зачем

Пустой лист заставляет клиента писать «сделайте красиво» и переносить уточнения в переписку. Форма заявки — это **набор полей под ситуацию**: клиент заполняет обязательные данные, а задача сразу приходит структурированной.

Классические шаблоны (`task_templates`: готовый HTML + чек-лист) остались, но вынесены в отдельный раздел **«Сценарии»**. Новый основной путь клиента — **«Формы заявок»**.

## Модель

`form_templates`:

- `key` — стабильный slug (`bug-report`, `website-brief`, …);
- `name`, `summary`, `description`, `icon`, `tone`, `category`;
- `schema` — JSON:
  - `fields[]` — поля верхнего уровня;
  - `groups[]` — повторяемые группы (`repeatable`, `min`, `max`, `add_label`);
- `is_active`, `sort_order`, `usage_count`.

Поле: `key`, `label`, `type`, `required`, `placeholder`, `help`, `options[]`.
Типы: `text`, `textarea`, `select`, `checkbox`, `date`, `number`, `url`, `email`, `image`.

`tasks` получил:

- `form_template_id` — nullable FK;
- `form_data` — JSON `{fields: {...}, groups: {key: [{...}]}}`.

Старые задачи и сценарии не затронуты.

## Поток клиента

1. `/tasks/create` — библиотека форм, выбранная форма и живая форма справа.
2. Клиент заполняет обязательные поля и повторяемые группы.
3. Изображения загружаются сразу через `POST /files/upload` (FileAttachment UUID), а не в общем multipart-запросе.
4. `POST /tasks/forms/{formTemplate}` → `FormTemplateService::createTask()`.
5. Задача создаётся через `CreateTaskAction`, `source=client`, `status=pending_review`, с `form_data` и `form_template_id`.
6. На странице задачи блок «Структурированная заявка» показывает поля и repeat-группы; описание остаётся коротким контекстом формы.

Классические сценарии: `/tasks/scenarios`; применение идёт через существующий `client.tasks.from-template`.

## Админ

- `/admin/forms` — библиотека форм: структура, количество полей, обязательные, repeat-группы, включение/выключение.
- `/admin/task-templates` — «Сценарии» (классические).
- Оба раздела доступны из навигации «Библиотека» и вкладок «Формы / Сценарии».

## Валидация

`FormTemplateService` строит правила из `schema`:

- обязательные поля → `required`;
- `select` → `Rule::in(options)`;
- `image` → UUID + владелец файла + MIME `image/*`;
- repeat-группы → массив, `min/max`, обязательные поля внутри;
- `checkbox` с `required` → `accepted`.

Ошибки приходят в Inertia по путям `form_data.fields.*` и `form_data.groups.*.0.*` и подсвечиваются в `StructuredForm`.

## Сиды

`database/seeders/FormTemplateSeeder.php`:

- Баг-репорт
- Бриф на сайт
- Контент-бриф
- Дизайн-ревью
- Доступы
- Интеграция
- Описание задачи + изображения (repeat «описание + изображение»)
- Фича-реквест

## Где смотреть

- [FormTemplate.php](../../app/Models/FormTemplate.php), [FormTemplateService.php](../../app/Services/FormTemplateService.php).
- [Client/Tasks/Create.vue](../../resources/js/Pages/Client/Tasks/Create.vue), [StructuredForm.vue](../../resources/js/Components/FormTemplates/StructuredForm.vue), [StructuredFormSummary.vue](../../resources/js/Components/FormTemplates/StructuredFormSummary.vue).
- [Admin/Forms/Index.vue](../../resources/js/Pages/Admin/Forms/Index.vue).
- [FormTemplateSeeder.php](../../database/seeders/FormTemplateSeeder.php).
