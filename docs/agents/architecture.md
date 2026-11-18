# Архитектура и домен

## Поток запроса

`routes/web.php` → Controller → FormRequest → Action/Service → Eloquent → Event → Listener.

Контроллер тонкий: принимает запрос, авторизует и делегирует. Бизнес-правила — в Action/Service, не в контроллере и не во Vue.

API идёт отдельным контуром: `routes/api.php` → `api.token` → `api.ability` → API-контроллер → тот же Action/Service. MCP-сервер не ходит в БД: он вызывает REST API с токеном, поэтому права и валидация не дублируются.

## Слои

- `app/Actions/` — операции: клиенты, задачи, счета, платежи, ручное подтверждение перевода.
- `app/Services/` — доменные сервисы (`TaskService`, `InvoiceService`, `SettingsService`, `YooKassaService`), новые: `TaskTemplateService`, `CustomFieldService`, `HtmlSanitizer`, `ApiTokenService`.
- `app/Services/Ai/` — `AgentService` (адаптер ядра), `SessionService` (список сессий с fallback на зеркало), `KernelClient`, `CrmToolbox`, `PromptFactory` (профиль роли), `TemplateGenerator` (детерминированный черновик).
- `app/Http/Controllers/Internal/AssistantToolController.php` — server-to-server инструменты для ядра.
- `app/Http/Requests/` — валидация web-форм; API валидирует в контроллерах, потому что правила часто зависят от роли.
- `app/Http/Resources/` — стабильные ответы API v1 (`ClientResource`, `TaskResource`, `TaskTemplateResource`, `InvoiceResource`, `CustomFieldResource`).
- `app/Policies/` — авторизация по моделям; API дополнительно скоупит клиентские токены.
- `app/Enums/` — `UserRole`, `TaskStatus`, `TaskType`, `TaskPriority`, `TaskSource`, `InvoiceStatus`, `PaymentMethod`, `CustomFieldEntity`, `CustomFieldType`, `AiActionStatus`, `AiMessageRole`, `ApiAbility`.
- `app/Events` + `app/Listeners` — уведомления, история статусов, клонирование maintenance-задачи, отмена счетов.

## Роли и доступ

- `admin` — клиенты, задачи, шаблоны, счета, настройки, API-токены, Кики.
- `client` — только свои задачи/счета/поля, создание заявки, оценка работы, оплата; Кики видит только его данные.
- Middleware: `is_admin`, `is_active`, `api.token`, `api.ability`, `yookassa_webhook`.
- Админские маршруты `/admin/*`; клиентские `/dashboard`, `/tasks`, `/invoices`, `/profile`; API `/api/v1/*`; MCP — отдельный Node-процесс.

## Домен

- Задача проходит `pending_review → queued → in_progress → awaiting_review → completed` с ветками отклонения. `source` показывает происхождение: `manual`, `template`, `client`, `ai`, `api`.
- Шаблон задачи хранит HTML-описание, чек-лист, тип/приоритет/статус по умолчанию, необязательные исполнителя и срок. Применение создаёт задачу с чек-листом и увеличивает `usage_count`.
- Пользовательские поля — `custom_fields` + типизированные `custom_field_values` (text/number/date/boolean/json), с дефолтами и API-представлением.
- Счёт: `pending → paid | cancelled | refunded`; `payment_method`:
  - `yookassa` — онлайн-оплата, вебхук, рекуррент;
  - `transfer` — реквизиты из `settings`, оплата вне кассы, рекуррент запрещён, подтверждение вручную.
- Кики: сессии и их заголовки принадлежат ядру; CRM хранит зеркало (`ai_conversations`), сообщения (`ai_messages`, включая ссылки на изображения) и журнал действий (`ai_actions`). Финансовые инструменты и заявки из форм создают `proposed`-действие и выполняются только после confirm; права перепроверяются.
- API-токены: `api_tokens` (SHA-256 хеш, префикс, abilities, expiry). Токен наследует роль пользователя.

## Где что искать

- Шаблоны и WYSIWYG — [project-management.md](project-management.md).
- Поля и API-значения — [custom-fields.md](custom-fields.md).
- Ассистент — [ai.md](ai.md).
- REST — [api.md](api.md), MCP — [mcp.md](mcp.md).
- Решения — [docs/adr](../adr/README.md).

## Куда добавлять фичу

1. Маршрут в `routes/web.php` или `routes/api.php` в нужную группу.
2. FormRequest (web) или валидация в контроллере (API).
3. Action/Service с бизнес-логикой.
4. Тонкий контроллер + Policy/скоуп.
5. Страница в `resources/js/Pages/{Admin,Client}/…`; переиспользуйте `Components/UI`, `Components/Form`, `Components/CustomFields`.
6. Тест в `tests/Feature` и, если меняется контракт, `mcp/src/tools.mjs` + `mcp/test`.

## Один интерфейс для всех пользователей

- Пользовательский лейаут один: `ClientLayout` → `AppShell`; отдельного
  `ExecutorLayout` нет. Админ пользуется `AdminLayout`.
- Навигация не ветвится по исполнительской роли: `AppShell` содержит один
  массив разделов для не-админов.
- Общие блоки выносятся в компоненты (`TaskStatusCard`, `TaskFormFields`,
  `StructuredForm`), а не копируются в страницы ролей.
- Проверка паритета: скилл `inteli-dev-crm-role-parity-audit`
  (`.agents/skills/.../audit_role_parity.py`). Запускать после правок
  навигации, лейаутов и общих карточек.
- Бизнес-логика важнее DRY: различия в правах (приёмка клиентом, отчёт
  исполнителя) остаются раздельными и фиксируются в allowlist скилла.

## Self-проекты

`projects.type` — `client` (обычный, с заказчиком) или `self` (внутренний).

- В self-проекте `client_id` всегда администратор, форма создания прячет выбор клиента.
- Задачи стартуют в статусе «В работе», а не «Новый»; согласие на обработку данных не спрашивается.
- Приёмка не нужна: исполнитель или заказчик завершают задачу напрямую, статус
  «ожидает оценки» пропускается (`TaskStrictControl`).

## Баланс

- `users.balance` — деньги пользователя, могут уходить в минус.
- `balance_transactions` — журнал: ручные корректировки админа (`reason=manual`) и
  автосписания за завершённые задачи (`reason=task_completed`).
- `BalanceService::settleCompletedTask()` вызывается из `ChangeTaskStatusAction` при
  переходе в «Завершено»: если у задачи есть бюджет, создаётся счёт «прямой перевод»
  и сумма списывается с баланса заказчика. При нулевом бюджете счёт не создаётся,
  повторное завершение не дублирует счёт (проверка по `invoices.task_id`).

## Роли: администратор и пользователь

Роли «клиент» и «исполнитель» объединены. В `users.role` остались `admin` и `user`
(значения `client`/`executor` миграция переводит в `user`).

- Кем человек выступает — решает назначение: `tasks.client_id`, `tasks.assignee_id`,
  `project_members.delegation`. Один и тот же пользователь может быть заказчиком
  в одном проекте и исполнителем в другом.
- `User::isClient()` и `User::isExecutor()` для обычного пользователя возвращают `true`:
  он имеет доступ и к клиентским, и к исполнительским разделам, и к своему кабинету.
- Админ-панель: разделы «Клиенты» и «Команда» слиты в «Пользователи» (`/admin/clients`,
  заголовок «Пользователи»); создание аккаунта одно, без выбора роли.
- Инструменты ассистента фильтруются через `User::roleNames()`: обычный пользователь
  получает и клиентские, и исполнительские инструменты.
- Домашняя страница у обычного пользователя одна — `/dashboard`; логин и сброс пароля
  ведут туда, а не в `/executor`.

## Календарь нагрузки

Нагрузка — это только незавершённая работа: `completed` и `rejected_by_admin` не попадают ни в календарь, ни в модалку дня, ни в интервалы.

`/calendar/intervals`, `/calendar/tasks`, `/calendar/activity` всегда скоупятся на исполнителя: по умолчанию —
текущий пользователь, `assignee_id` принимается только от админа. Поэтому виджет «Календарь активности» и на «Обзоре»,
и в панели виджетов показывает свои задачи, а не всю команду.

Новый виджет «Нагрузка сотрудника» (только админ) — тот же календарь с `<select>` сотрудников: админ выбирает человека
и видит его загрузку по дням. У исполнителя на «Обзоре» есть компактный календарь «Моя нагрузка» с его задачами.

## Приёмка задач и строгий контроль

Статусы задачи — `App\Enums\TaskStatus`: `pending_review`, `queued`, `in_progress`, `awaiting_review`,
`rejected_by_client`, `completed`, `rejected_by_admin`.

Решение «кто может двигать статус» вынесено в `App\Services\Tasks\TaskStrictControl`:

- **Строгий контроль** (настройка CRM `task_strict_control`, по умолчанию включён) — штатная приёмка:
  «Ожидает оценки» → «Завершено» ставит только клиент. Переключается в «Настройки → Задачи», доступен только админу.
- **Мягкий режим** (`task_strict_control=0`) — исполнитель может закрыть задачу сам: тот же переход
  «Ожидает оценки» → «Завершено» разрешён и ему. Другие переходы не ослабляются.
- **Переопределение на задаче** — `tasks.strict_control` (`null` — наследовать настройку, `true/false` — зафиксировать).
  Задаётся в форме задачи полем «Контроль приёмки».
- **Админ вне правил** — может перевести задачу в любой статус, включая обратный переход из «Завершено».

Все точки смены статуса идут через `ChangeTaskStatusAction` и `TaskStrictControl`: веб-интерфейс админа и исполнителя,
`update_task_status` в MCP и Кики. Клиент принимает работу через `client.tasks.review`, и это правило не меняется
ни одним переключателем.
