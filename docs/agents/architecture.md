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
