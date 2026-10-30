# CRM для самозанятого

Single-tenant CRM: админ ведёт клиентов, задачи, счета и оплаты, клиент видит свои задачи и счета и платит через ЮКассу или прямым переводом. Регистрации нет — аккаунты клиентов создаёт админ.

Стек: Laravel 13 · PHP 8.4 · Inertia 3 · Vue 3.5 · Vite 8 · Tailwind 4 · PostgreSQL 16 · Redis 7 · Pest 4 · Pint. Локально и в проде — Docker Compose.

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
| Поднять окружение, env, миграции | [setup.md](docs/agents/setup.md) |
| Контроллеры, Actions/Services, роли, статусы, две схемы оплаты | [architecture.md](docs/agents/architecture.md) |
| Vue/Inertia, тема, лейауты, сборка ассетов | [frontend.md](docs/agents/frontend.md) |
| Тесты, Pint, чем закрывать изменение | [testing.md](docs/agents/testing.md) |
| Прод-деплой, образы, публикация ассетов | [deployment.md](docs/agents/deployment.md) |
| 502, opcache, дрейф доков и прочие ловушки | [gotchas.md](docs/agents/gotchas.md) |
| Бизнес-требования и сущности | [.github/instructions/terms_of_reference.instructions.md](.github/instructions/terms_of_reference.instructions.md) |
| Дизайн-система и макеты | [.superdesign/design-system.md](.superdesign/design-system.md) |

## Что помнить всегда

- Бизнес-логика — в Action/Service; контроллер принимает запрос, авторизует и делегирует.
- Статусы, роли и способы оплаты — нативные enum из `app/Enums`.
- UI — из `Components/UI` и `Components/Form`; тема задаётся токенами `resources/css/app.css`.
- Проверка изменения — ручной сценарий из [testing.md](docs/agents/testing.md): набора `tests/` в репозитории нет.
- Секреты — только в `.env`; `.env*` не коммитится.
