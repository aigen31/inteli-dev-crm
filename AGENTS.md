# AGENTS.md

CRM для самозанятого: single-tenant веб-приложение, где админ ведёт клиентов, задачи, счета и оплаты через ЮКассу. Регистрации нет — аккаунты клиентов создаёт админ.

Стек (фактический, версии из `composer.lock` / `package-lock.json`):
Laravel 13 · PHP 8.4 · Inertia 3 · Vue 3.5 · Vite 8 · Tailwind 4 · PostgreSQL 16 · Redis 7 · Pest 4 · Laravel Pint.
Прод и локальный запуск — Docker Compose (`docker-compose.yml` + override для локали).

## Быстрый старт (Docker)

```bash
cp .env.example .env
# APP_KEY обязателен; сгенерируйте без локального PHP:
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
# DB_PASSWORD и ACME_EMAIL обязательны: compose подставляет их ещё до фильтрации профилей
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 16)|" .env
sed -i "s|^ACME_EMAIL=.*|ACME_EMAIL=admin@example.com|" .env
# APP_URL/APP_DOMAIN — localhost, APP_PORT — свободный порт (80 на этой машине занят)
sed -i "s|^APP_URL=.*|APP_URL=http://localhost:8099|; s|^APP_DOMAIN=.*|APP_DOMAIN=localhost|; s|^APP_PORT=.*|APP_PORT=8099|" .env

docker compose build
docker compose up -d --wait
docker compose exec app php artisan db:seed --force
```

- Приложение: `http://localhost:${APP_PORT}` (здесь — `http://localhost:8099`), healthcheck `/up`.
- Админ из `database/seeders/AdminSeeder.php`: `admin@inteli-dev.ru` / `eJUzCT693su%`. Смените пароль после первого входа.
- Логи: `docker compose logs -f app nginx worker scheduler`.
- Остановка: `docker compose down`; с данными — `docker compose down -v`.

`docker-compose.override.yml` отключает Traefik (профиль `production`), публикует nginx на `APP_PORT` и Postgres на `127.0.0.1:${DB_FORWARD_PORT}`.

Если `~/.docker` недоступен для записи (песочница), скопируйте его в `.docker-config/` и запускайте команды с `DOCKER_CONFIG=$PWD/.docker-config`.

## Разработка без Docker

Нужны PHP 8.3+, Composer, Node 22+, PostgreSQL и Redis.

```bash
composer setup   # install, .env, key, migrate, npm install, npm run build
composer dev     # serve + queue:listen + pail + vite concurrently
```

Для точечных команд: `php artisan migrate`, `php artisan queue:work`, `npm run dev`, `npm run build`.
Фронтенд без `public/build` не отрисуется: после правок Vue/CSS запускайте `npm run build` (или держите `npm run dev`).

## Тесты и линт

- `tests/` в репозитории отсутствует, хотя `phpunit.xml` ждёт `tests/bootstrap.php`, `tests/Unit`, `tests/Feature`. Поэтому `php artisan test` сейчас не запускается. При добавлении тестов — Pest 4, тестовая БД sqlite `:memory:` (см. `phpunit.xml`).
- В production-образе dev-зависимостей нет: тесты гоняйте в локальном окружении с `composer install`.
- Стиль PHP: `./vendor/bin/pint --dirty` (Laravel Pint). В новом коде придерживайтесь `declare(strict_types=1)` и нативных enum.

## Архитектура

Поток запроса: `routes/web.php` → Controller → FormRequest (валидация) → Action/Service (бизнес-логика) → Eloquent-модель → Event → Listener (уведомления, история).

- `app/Actions/` — одноразовые операции (создание задачи/счёта, смена статуса, платёж, возврат).
- `app/Services/` — `TaskService`, `InvoiceService`, `FileUploadService`, `YooKassaService`.
- `app/Http/Requests/` — вся валидация; контроллеры тонкие, без бизнес-логики.
- `app/Policies/` — авторизация по моделям; не дублируйте проверки ролей в контроллерах.
- `app/Enums/` — `UserRole`, `TaskStatus`, `TaskType`, `TaskPriority`, `InvoiceStatus`, `RecurringInterval`; статусы в БД хранятся строками этих enum.
- `app/Events` + `app/Listeners` — побочные эффекты: уведомления, `TaskStatusHistory`, клонирование maintenance-задачи, отмена счетов.
- Роли: `admin` и `client`. Middleware-алиасы в `bootstrap/app.php`: `is_admin`, `is_active`, `yookassa_webhook`. Клиент видит только свои задачи/счета (`/tasks`, `/invoices`), админ — `/admin/*`.

Домен: задача (`one_time` / `maintenance`) проходит статусы `pending_review → queued → in_progress → awaiting_review → completed` с ветками отклонения; счёт — `pending → paid | cancelled | refunded`. Суммы — `decimal(10,2)` в рублях. Автоплатежи и напоминания — команды `payments:process-recurring`, `invoices:send-reminders`, `invoices:cancel-expired`, расписание в `routes/console.php`. Webhook ЮКассы: `POST /webhooks/yookassa` без auth, защищён `VerifyYooKassaWebhook`; ключи — `YOOKASSA_SHOP_ID`, `YOOKASSA_SECRET_KEY` (`config/services.php`).

Куда добавлять фичу: маршрут в `routes/web.php` (в нужную группу) → контроллер → FormRequest → Action/Service → страница в `resources/js/Pages/{Admin,Client}/…`.

## Фронтенд

- Inertia-страницы: `resources/js/Pages/Admin`, `resources/js/Pages/Client`; layouts и UI-кит — `resources/js/Components/{Layout,UI,Form}`.
- Роутинг на клиенте — глобальный `route()` из Ziggy (`@routes` не нужен, данные приходят через `HandleInertiaRequests`).
- Алиас `@` → `resources/js` (`vite.config.js`).
- Tailwind 4: токены темы (`--color-primary-*`, шрифты) объявлены в `resources/css/app.css` через `@theme`; `tailwind.config.js` нет. Не создавайте его и не следуйте старым инструкциям про Tailwind 3.
- Root-view — `resources/views/app.blade.php` (`@vite`, `@inertia`).

## Деплой

```bash
# локально: .env.production с APP_ENV=production, APP_DEBUG=false, ACME_EMAIL, APP_KEY, DB_PASSWORD
./deploy.sh
```

`deploy.sh` синхронизирует проект rsync-ом на `crm-prod:/home/outline_kz/crm` (исключая `.git`, `vendor`, `node_modules`, `.env*`, `*.md`), заливает `.env.production` как `.env`, собирает образы и поднимает compose. Прод-контур: Traefik 3.4 + Let's Encrypt, домены `crm.inteli-dev.ru` (CRM) и `inteli-dev.ru` (лендинг из `promo/` на порту 8080), внешняя сеть `traefik-public`.

Entrypoint production-образа кеширует config/routes/views и выполняет `migrate --force` при старте. После изменения `.env` или конфигов контейнер `app` нужно перезапустить, иначе останется старый кеш.

## Подводные камни

- `.github/instructions/*.md` — полезный источник бизнес-требований и UI-кита, но они устарели по стеку (там Laravel 11, Inertia 1, Tailwind 3) и упоминают несуществующие файлы (`AuthServiceProvider`, `EventServiceProvider`, `RetryFailedPaymentsCommand`). Источник истины — код.
- Compose требует `ACME_EMAIL` и `DB_PASSWORD` даже для локального запуска: интерполяция выполняется до отключения Traefik профилем.
- `bootstrap/cache/`, `storage/framework/{sessions,views}`, `storage/logs/` должны существовать в git (добавлены `.gitignore`-заглушки) — иначе сборка падает на `composer dump-autoload`, а Laravel не пишет кеш/логи.
- nginx прокидывает `X-Forwarded-Proto` и вычисляет `HTTPS` из запроса. Не хардкодьте `fastcgi_param HTTPS on` — локально это уводит редиректы на несуществующий https.
- `public/build` не коммитится и собирается внутри образа; локально его надо собирать самому.
- Промо-лендинг (`promo/`) — отдельный статический сайт в том же compose, не часть Inertia-приложения.

## Документация

- `.github/instructions/terms_of_reference.instructions.md` — ТЗ, роли, сущности, бизнес-правила. Читать перед изменением доменной логики.
- `.github/instructions/project_strucrure.instructions.md` — карта каталогов и слоёв (с оговоркой про дрейф версий).
- `.github/instructions/design.instructions.md` — палитра, типографика, компоненты UI. Читать перед правкой Vue-страниц и стилей.
- `.github/instructions/landing.instructions.md` — структура и тексты лендинга.
