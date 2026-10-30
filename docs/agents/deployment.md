# Деплой и сборка образов

## Production

```bash
./deploy.sh
```

Скрипт требует SSH к `crm-prod` и `.env.production`. Он rsync-ит проект (без `.git`, `vendor`, `node_modules`, `.env*`, `*.md`, `public/build`), копирует `.env.production` как `.env`, собирает образы и проверяет `/up`.

Прод: Traefik 3.4 + Let's Encrypt, `crm.inteli-dev.ru` и лендинг `inteli-dev.ru` (`promo/`, порт 8080), внешняя сеть `traefik-public`.

## Entrypoint

При старте `app`:

1. копирует `public-build` из образа в общий volume;
2. кеширует config/routes/views/events;
3. выполняет `migrate --force`.

После изменения `.env` перезапустите `app`, иначе останется старый config-кеш.

## Образ и ассеты

`docker compose build` вшивает PHP-код, `vendor/`, blade и `public/build`. Рестарт `app` возвращает версию ассетов из образа, поэтому финальные правки закрепляйте пересборкой:

```bash
docker compose build
docker compose up -d --wait
```

Для быстрой локальной проверки можно скопировать `public/build` и `public/images` в контейнер **после** рестарта.

## bootstrap/cache

`bootstrap/cache/*.php` исключён в `.dockerignore` и `.gitignore`. Это не косметика: host-кеш содержит `Laravel\Pail\PailServiceProvider` из dev-зависимостей, а production-образ собирается без Pail и падает на старте с `Class "Laravel\Pail\PailServiceProvider" not found`. Если образ уже собран с кешем — пересоберите после `rm -f bootstrap/cache/*.php`.

## MCP

MCP-сервер — отдельный Node-процесс, не часть PHP-образа. В прод-контуре запускайте его рядом с CRM (или на машине оператора) с `CRM_API_URL` и токеном; наружу порт не публикуйте.

После пересоздания `app` nginx может держать старый IP — перезапустите `nginx` (см. [gotchas.md](gotchas.md)).
