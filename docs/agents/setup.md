# Локальный запуск

## Docker (основной путь)

Нужны Docker и Docker Compose. Проект поднимается вместе с PostgreSQL 16, Redis 7, nginx, queue worker и scheduler.

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

- `APP_KEY`, `DB_PASSWORD` и `ACME_EMAIL` обязательны: compose подставляет их до того, как отключит Traefik профилем.
- `APP_PORT` — свободный порт на хосте (в этом workspace занят 8099); `DB_FORWARD_PORT` — порт Postgres на `127.0.0.1`.
- Приложение: `http://localhost:${APP_PORT}`, healthcheck `/up`.
- Админ из `database/seeders/AdminSeeder.php`: `admin@inteli-dev.ru` / `eJUzCT693su%`.
- Миграции выполняет entrypoint при старте `app`; вручную — `docker compose exec app php artisan migrate`.
- Логи: `docker compose logs -f app nginx worker scheduler`.
- Остановка: `docker compose down`; с данными — `docker compose down -v`.

`docker-compose.override.yml` отключает Traefik (профиль `production`), публикует nginx на `APP_PORT` и Postgres на `DB_FORWARD_PORT`.

Если `~/.docker` недоступен для записи (песочница), скопируйте его в `.docker-config/` и запускайте команды с `DOCKER_CONFIG=$PWD/.docker-config`.

## Без Docker

Нужны PHP 8.4+, Composer, Node 24+, локальные PostgreSQL и Redis.

```bash
composer setup   # install, .env, key, migrate, npm install, npm run build
composer dev     # serve + queue:listen + pail + vite concurrently
```

Точечные команды: `php artisan migrate`, `php artisan queue:work`, `npm run dev`, `npm run build`.

Локальный `npm run build` читает `vendor/tightenco/ziggy`; без Composer возьмите `vendor/` из контейнера: `docker compose cp app:/var/www/vendor ./vendor`.

## После изменений

- Vue/CSS: `npm run build` (или `npm run dev`).
- PHP/blade: пересоберите образ или скопируйте файл в контейнер и перезапустите `app` — см. [deployment.md](deployment.md).
- Если после пересоздания `app` nginx отдаёт 502, перезапустите `nginx` — см. [gotchas.md](gotchas.md).
