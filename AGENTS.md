# CRM для самозанятого

Single-tenant рабочее пространство: клиенты, задачи, шаблоны задач, счета и оплаты. Админ ведёт практику, клиент видит только свои задачи и счета. Регистрации нет — аккаунты создаёт админ. Поверх домена работают REST API, MCP-сервер и ассистент Кики.

Стек: Laravel 13 · PHP 8.5 · Inertia 3 · Vue 3.5 · Vite 8 · Tailwind 4 · TipTap · PostgreSQL 16 · Redis 7 · Pest 5 · Pint. Локально и в проде — Docker Compose.

## Быстрый старт

```bash
cp .env.example .env   # APP_KEY, DB_PASSWORD, ACME_EMAIL обязательны
docker compose build && docker compose up -d --wait
docker compose exec app php artisan db:seed --force
```

Приложение — `http://localhost:${APP_PORT}` (в этом workspace — 8099), healthcheck `/up`. Полная настройка, порты и запуск без Docker — [setup.md](docs/agents/setup.md).

## Куда читать дальше

| Задача | Документ |
|---|---|
| Поднять окружение, env, миграции, тесты | [setup.md](docs/agents/setup.md) |
| Слои, роли, статусы, оплаты, границы домена | [architecture.md](docs/agents/architecture.md) |
| Шаблоны задач, WYSIWYG, чек-листы, ИИ-генерация | [project-management.md](docs/agents/project-management.md) |
| Пользовательские поля, типы, значения, API | [custom-fields.md](docs/agents/custom-fields.md) |
| Кики: провайдер, инструменты, подтверждения, guardrails | [ai.md](docs/agents/ai.md) |
| REST API v1, токены, примеры | [api.md](docs/agents/api.md) · [docs/api](docs/api/README.md) |
| MCP-сервер и подключение ИИ-клиентов | [mcp.md](docs/agents/mcp.md) · [mcp/README.md](mcp/README.md) |
| Vue/Inertia, дизайн-система, лейауты, сборка | [frontend.md](docs/agents/frontend.md) |
| Pest, Pint, MCP-тесты, чем закрывать изменение | [testing.md](docs/agents/testing.md) |
| Прод-деплой, образы, публикация ассетов | [deployment.md](docs/agents/deployment.md) |
| 502, opcache, bootstrap/cache, дрейф доков | [gotchas.md](docs/agents/gotchas.md) |
| Архитектурные решения | [docs/adr](docs/adr/README.md) |
| Исследование PMS/CRM/AI-паттернов | [docs/research](docs/research/pms-landscape.md) |
| Бизнес-требования | [.github/instructions/terms_of_reference.instructions.md](.github/instructions/terms_of_reference.instructions.md) |
| Дизайн-система | [.superdesign/design-system.md](.superdesign/design-system.md) |

## Что помнить всегда

- Бизнес-логика — в Action/Service; контроллер принимает запрос, авторизует и делегирует.
- Статусы, роли, типы полей и способы оплаты — нативные enum из `app/Enums`.
- HTML из WYSIWYG и ИИ проходит `HtmlSanitizer`; не выводите сырой HTML без санитайза.
- Пользовательские поля — только через `CustomFieldService`; значения хранятся типизированно, не в одном JSON.
- API-токен наследует роль пользователя; `read` не даёт писать, `write` нужен для действий Кики через API.
- Финансовые действия Кики выполняются только после подтверждения (`AiAction`), права перепроверяются при confirm.
- UI — из `Components/UI`, `Components/Form`, `Components/CustomFields`; тема — токены `resources/css/app.css`.
- Проверка изменения: `php artisan test` в контейнере + `vendor/bin/pint --test`; MCP — `cd mcp && npm test`.
- Секреты — только в `.env` и зашифрованных настройках; `.env*` и `bootstrap/cache/*.php` не коммитятся.
