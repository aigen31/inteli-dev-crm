# Локальный запуск

## Docker (основной путь)

Нужны Docker и Docker Compose. Поднимаются app (PHP-FPM), nginx, PostgreSQL 16, Redis 7, queue worker и scheduler.

```bash
cp .env.example .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 16)|" .env
sed -i "s|^ACME_EMAIL=.*|ACME_EMAIL=admin@example.com|" .env
sed -i "s|^APP_URL=.*|APP_URL=http://localhost:8099|; s|^APP_DOMAIN=.*|APP_DOMAIN=localhost|; s|^APP_PORT=.*|APP_PORT=8099|" .env

docker compose build
docker compose up -d --wait
docker compose exec app php artisan db:seed --force
```

- `APP_KEY`, `DB_PASSWORD`, `ACME_EMAIL` обязательны: compose интерполирует их до отключения Traefik профилем.
- Приложение: `http://localhost:${APP_PORT}`, healthcheck `/up`.
- Админ из `AdminSeeder`: `admin@inteli-dev.ru` / `eJUzCT693su%`. Демо-клиент: `test@client.ru` / `secret123`.
- `ProjectManagementSeeder` создаёт 5 шаблонов задач и пользовательские поля (включая `intake_status`). Сидер идемпотентен.
- Миграции выполняет entrypoint; вручную — `docker compose exec app php artisan migrate`.
- Логи: `docker compose logs -f app nginx worker scheduler`.

`docker-compose.override.yml` отключает Traefik, публикует nginx на `APP_PORT`, Postgres на `DB_FORWARD_PORT`.

Если `~/.docker` недоступен для записи, используйте `DOCKER_CONFIG=$PWD/.docker-config`.

## Без Docker

Нужны PHP 8.5+, Composer, Node 24+, PostgreSQL и Redis.

```bash
composer setup   # install, .env, key, migrate, npm install, npm run build
composer dev     # serve + queue:listen + pail + vite
```

`npm run build` читает `vendor/tightenco/ziggy`; без Composer возьмите `vendor/` из контейнера.

## Тесты

В репозитории есть `tests/`. Production-образ без dev-зависимостей, поэтому тесты запускайте отдельным контейнером с host-`vendor/`:

```bash
rm -f bootstrap/cache/*.php
docker run --rm --network inteli-dev-crm_crm --env-file .env \
  -v "$PWD":/var/www -w /var/www --entrypoint php inteli-dev-crm-app \
  artisan test --compact
```

MCP-тесты — `cd mcp && npm test` (Node 20+, без зависимостей).

## ИИ и MCP

- Кики настраивается в UI: **Настройки → Кики** (Base URL, модель, ключ). Ключ хранится зашифрованным в `settings`.
- Прямой OpenAI-клиент удалён. Модель и ключи задаются только в центральном ядре (`KERNEL_LOCAL_*` / `KERNEL_CLOUD_*`).
- MCP-сервер запускается отдельно: `cd mcp && CRM_API_URL=... CRM_API_TOKEN=... npm start`.
- API-токен создаётся в **Настройки → API и MCP**; он показывается один раз.

## После изменений

- Vue/CSS: `npm run build` (или `npm run dev`).
- PHP/blade: пересоберите образ или скопируйте файл и перезапустите `app` (opcache).
- После пересоздания `app` nginx может отдавать 502 — перезапустите `nginx`.
