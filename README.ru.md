# inteli dev CRM

[English](README.md) · [Русский](README.ru.md) · [中文](README.zh_CN.md)

<img src="public/images/kiki/kiki-flight-avatar.webp" width="110" align="right" alt="Кики — ассистент inteli dev">

Рабочее пространство для самозанятого: клиенты, задачи, проекты, шаблоны, счета, отчёты и ассистент Кики — в одном месте.

CRM закрытая: регистрации нет, аккаунты создаёт владелец. Администратор ведёт практику, клиент видит только свои задачи, счета и отчёты.

## Возможности

- **Клиенты и задачи** — статусы, приоритеты, дедлайны, чек-листы, комментарии и история изменений.
- **Проекты** — ключи вида `KOR`, клиент, участники и роли, прогресс, сайт, `AGENTS.md` и изучение сайта.
- **Шаблоны задач и формы заявок** — повторяемые сценарии, обязательные поля и структурированные заявки.
- **Счета и оплаты** — ЮKassa, прямые переводы, рекуррентные платежи и напоминания.
- **Отчёты клиентам** — группы «объяснение → ссылка для проверки → скриншот», получатели и непрочитанные.
- **Библиотека** — материалы и документы рабочего пространства.
- **Кики** — ассистент с доступом к CRM через инструменты: находит клиентов и задачи, готовит планы, создаёт заявки и помогает с интерфейсом.
- **Помощь** — карта разделов CRM с шагами и ссылками.
- **API и MCP** — REST API v1 и MCP-сервер для внешних ИИ-клиентов.
- **Правовые страницы** — политика конфиденциальности и согласие на обработку данных.

## Стек

| Слой | Технологии |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Vue 3.5, Inertia 3, Vite 8, Tailwind CSS 4 |
| База | PostgreSQL 16, Redis 7 |
| Качество | Pest 5, Pint |
| Инфраструктура | Docker Compose, nginx, Traefik, Let's Encrypt |

## Быстрый старт

Требуются Docker и Docker Compose.

```bash
git clone git@github.com:aigen31/inteli-dev-crm.git
cd inteli-dev-crm

cp .env.example .env
# Укажите APP_KEY, DB_PASSWORD и остальные обязательные значения

docker compose build
docker compose up -d --wait
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

Приложение откроется по адресу `http://localhost:${APP_PORT}`.
Healthcheck: `GET /up`.

Полезные команды:

```bash
# Логи приложения
docker compose logs -f app

# Тесты
docker compose exec app php artisan test

# Стиль кода
docker compose exec app vendor/bin/pint --test

# Frontend-сборка
npm run build
```

## Переменные окружения

Основные параметры находятся в `.env`:

- `APP_URL`, `APP_KEY`, `APP_ENV` — базовые настройки приложения.
- `DB_*` — подключение к PostgreSQL.
- `REDIS_*` — кеш, сессии и очереди.
- `KERNEL_URL`, `KERNEL_TOKEN`, `KERNEL_PROVIDER_TOKEN` — подключение к центральному ядру Кики.
- `YOOKASSA_*` — приём платежей.
- `MCP_TOKEN` — доступ к MCP-серверу.

Секреты не коммитятся. Для production используйте отдельный `.env`/`.env.production`.

## Архитектура

```text
app/
├── Actions/          # бизнес-операции: создание, смена статуса, оплата
├── Enums/            # роли, статусы, типы и способы оплаты
├── Http/
│   ├── Controllers/  # Admin, Client, Api/V1, Internal, Mcp
│   ├── Middleware/   # авторизация, API-токены, kernel-контракт
│   └── Requests/     # валидация входящих данных
├── Models/           # Eloquent-модели
├── Services/         # доменные сервисы и AI-интеграция
└── Policies/         # доступ к клиентам, задачам и проектам

resources/js/
├── Pages/            # Inertia-страницы Admin/Client/Assistant
├── Components/       # UI, формы, ассистент, Кики
├── Composables/      # состояние чата и общие сценарии
└── css/              # дизайн-токены и Tailwind

mcp/                  # MCP-сервер
docs/agents/          # документация для разработки
docker/               # Dockerfile, nginx, entrypoint
```

Бизнес-логика находится в `Actions`/`Services`, контроллеры отвечают за приём запроса, авторизацию и делегирование. Пользовательские поля проходят через `CustomFieldService`, HTML из WYSIWYG и ИИ — через `HtmlSanitizer`.

## Кики и внешние интеграции

Кики работает через центральное ядро (Kiki assistant kernel). CRM:

- отдаёт ядру системный промпт, список инструментов и контекст страницы;
- принимает вызовы инструментов по внутреннему контракту `/api/internal/assistant`;
- хранит зеркало диалогов, вложения, документы и подтверждения действий.

Финансовые действия Кики выполняются только после подтверждения пользователем; права перепроверяются в момент подтверждения.

REST API доступен по `/api/v1`, MCP — по `/api/mcp`. Подробнее: [docs/agents/api.md](docs/agents/api.md) и [docs/agents/mcp.md](docs/agents/mcp.md).

## Production

Production-стек использует Traefik, внешнюю сеть `traefik-public` и отдельный nginx-контейнер.

```bash
# локальная production-конфигурация без dev-override
docker compose -f docker-compose.yml up -d --wait

# миграции
docker compose -f docker-compose.yml exec app php artisan migrate --force
```

Для удалённого деплоя предусмотрен `deploy.sh`: он синхронизирует проект, собирает образы и проверяет `/up`. Перед запуском настройте SSH-доступ и `.env.production`.

## Документация

| Тема | Документ |
|---|---|
| Установка и окружение | [docs/agents/setup.md](docs/agents/setup.md) |
| Архитектура и домен | [docs/agents/architecture.md](docs/agents/architecture.md) |
| Проекты и задачи | [docs/agents/project-management.md](docs/agents/project-management.md) |
| Формы заявок | [docs/agents/form-templates.md](docs/agents/form-templates.md) |
| Пользовательские поля | [docs/agents/custom-fields.md](docs/agents/custom-fields.md) |
| Кики и AI | [docs/agents/ai.md](docs/agents/ai.md) |
| REST API | [docs/agents/api.md](docs/agents/api.md) |
| MCP | [docs/agents/mcp.md](docs/agents/mcp.md) |
| Frontend и дизайн | [docs/agents/frontend.md](docs/agents/frontend.md) |
| Тестирование | [docs/agents/testing.md](docs/agents/testing.md) |
| Деплой | [docs/agents/deployment.md](docs/agents/deployment.md) |

## Тестирование

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
cd mcp && npm test
```

Тесты используют SQLite `:memory:` (см. `phpunit.xml`) и не затрагивают production-базу.

## Роли

- **Администратор** — управляет клиентами, задачами, проектами, счетами, шаблонами, отчётами и доступами.
- **Клиент** — видит свои задачи, счета, отчёты и проекты, создаёт заявки и общается с Кики.

---

Сделано в [inteli dev](https://inteli-dev.ru).
