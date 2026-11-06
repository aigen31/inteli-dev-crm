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

## Ветки и деплой — обязательный порядок

Главное правило: **разработка идёт только в `dev`**. `master` и `production` не используются для новых изменений.

1. Начните с обновления `dev`:
   ```bash
   git switch dev
   git pull --ff-only origin dev
   ```
2. Делайте изменения и коммиты **только в `dev`**.
3. Перед мержем проверьте `dev`:
   ```bash
   docker compose exec app php artisan test
   docker compose exec app vendor/bin/pint --test
   npm run build
   ```
   Дополнительно пройдите smoke-проверку затронутых страниц: `/up`, вход, приглашение, настройки и изменённый раздел.
4. Только после зелёных тестов обновите `master` и `production` merge-коммитами:
   ```bash
   git switch master
   git merge --no-ff dev -m "Merge dev into master"

   git switch production
   git merge --no-ff master -m "Merge master into production"
   ```
5. Production-деплой выполняется **только после** шага 4: пересоберите образ, перезапустите `app` и проверьте `/up` и ключевые страницы.

- `dev` — единственная ветка для разработки.
- `master` — релизное зеркало для GitHub, обновляется из `dev` после тестов.
- `production` — ветка боевого деплоя, обновляется только merge-коммитом.
- Прямые коммиты и пуш в `master`/`production` в обход `dev` запрещены.
- Если тесты не прошли, исправления продолжаются в `dev`; мерж и деплой не выполняются.

## Куда читать дальше

| Задача | Документ |
|---|---|
| Поднять окружение, env, миграции, тесты | [setup.md](docs/agents/setup.md) |
| Слои, роли, статусы, оплаты, границы домена | [architecture.md](docs/agents/architecture.md) |
| Формы заявок: поля, обязательные, repeat-группы | [form-templates.md](docs/agents/form-templates.md) |
| Классические сценарии, WYSIWYG, чек-листы, ИИ-генерация | [project-management.md](docs/agents/project-management.md) |
| Пользовательские поля, типы, значения, API | [custom-fields.md](docs/agents/custom-fields.md) |
| Кики: сессии, изображения, документы, планы задач, память проекта | [ai.md](docs/agents/ai.md) |
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
| Иллюстрации и канон Кики | [kiki-assets.md](docs/agents/kiki-assets.md) |

## Что помнить всегда

- Бизнес-логика — в Action/Service; контроллер принимает запрос, авторизует и делегирует.
- Статусы, роли, типы полей и способы оплаты — нативные enum из `app/Enums`.
- HTML из WYSIWYG и ИИ проходит `HtmlSanitizer`; не выводите сырой HTML без санитайза.
- Пользовательские поля — только через `CustomFieldService`; значения хранятся типизированно, не в одном JSON.
- API-токен наследует роль пользователя; `read` не даёт писать, `write` нужен для действий Кики через API.
- Финансовые действия Кики выполняются только после подтверждения (`AiAction`), права перепроверяются при confirm.
- Кики: сессии и заголовки принадлежат ядру, CRM держит зеркало; изображения хранятся в `FileAttachment`, документы — в `ai_documents` (ядро получает манифест и читает через `read_document`); планы задач и формы подтверждаются как `AiAction`.
- Отчёты — повторяемые группы «объяснение → ссылка для тестирования → скриншот», получатель и опциональная задача. Создаются в CRM и через MCP (`/api/mcp`, инструмент `create_task_report`); DeepSeek Harness подключён к этому серверу.
- Проекты — отдельная сущность: ключ, клиент, задачи с номерами (`KOR-12`), прогресс, сайт, `AGENTS.md` и указания. Доступ к проекту — владелец + участники с ролями (`manager`/`member`/`viewer`); задачи клиента фильтруются и по проекту. Контекст и изучение сайта принадлежат проекту, а не общим настройкам; прайс-лист отдаёт ядро (`get_price_list`).
- UI — из `Components/UI`, `Components/Form`, `Components/CustomFields`; тема — токены `resources/css/app.css`.
- Проверка изменения: `php artisan test` в контейнере + `vendor/bin/pint --test`; MCP — `cd mcp && npm test`.
- Рабочая ветка — только `dev`; после тестов — merge в `master`/`production`, затем деплой.
- Секреты — только в `.env` и зашифрованных настройках; `.env*` и `bootstrap/cache/*.php` не коммитятся.
