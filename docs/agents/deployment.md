# Деплой и сборка образов

## Production

```bash
./deploy.sh
```

Скрипт требует SSH-доступ к `crm-prod` и локальный `.env.production` (в нём `APP_ENV=production`, `APP_DEBUG=false`, `ACME_EMAIL`, `APP_KEY`, `DB_PASSWORD`). Он:

1. синхронизирует проект rsync-ом в `/home/outline_kz/crm`, исключая `.git`, `vendor`, `node_modules`, `.env*`, `*.md`, `public/build`;
2. копирует `.env.production` как `.env`;
3. собирает образы и поднимает compose на сервере, проверяет `/up`.

Прод-контур: Traefik 3.4 + Let's Encrypt, домены `crm.inteli-dev.ru` (CRM) и `inteli-dev.ru` (лендинг из `promo/` на порту 8080), внешняя сеть `traefik-public`.

## Что делает entrypoint

Production-образ при старте кеширует config, routes, views и events, выполняет `migrate --force`, затем запускает процесс. После изменения `.env` или конфигов перезапустите `app`, иначе останется старый кеш.

## Образ и ассеты

`docker compose build` вшивает в образ PHP-код, `vendor/`, blade-шаблоны и собранный `public/build`. При старте entrypoint копирует `public-build` из образа в общий volume, который читает nginx.

Поэтому:

- локальная сборка ассетов видна сразу после `docker compose cp public/build/. app:/var/www/public/build`;
- любой рестарт `app` затирает эту копию версией из образа — финальную версию закрепляйте через `docker compose build` и `docker compose up -d --wait`;
- PHP/blade-правки в запущенном контейнере не подхватываются (код из образа, opcache с `validate_timestamps=0`): копируйте файл и перезапускайте `app` либо пересобирайте образ.

После пересоздания `app` nginx может держать его старый IP и отдавать 502 — перезапустите `nginx` (см. [gotchas.md](gotchas.md)).
