# Деплой CRM на прод (aaPanel + SSH)

Прод живёт на `root@152.228.118.128` в `/www/wwwroot/crm.inteli-dev.ru`.
Панель aaPanel проксирует `crm.inteli-dev.ru` на `127.0.0.1:18001` (контейнер nginx),
поэтому compose запускается с двумя файлами:

```bash
docker compose -f docker-compose.yml -f docker-compose.aapanel.yml ...
```

## Git и GitHub

Ключ `~/.ssh/id_ed25519` добавлен в GitHub (passphrase хранится у владельца).
Перед первым пушем загрузите его в агент:

```bash
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519
```

`deploy-prod.sh` пушит ветку:
- в `prod` (bare-репозиторий на сервере) — обязательная резервная копия;
- в `origin` (GitHub) — если доступен; при ошибке деплой продолжается.
Отключить пуш в GitHub: `DEPLOY_PUSH_ORIGIN=0`.

## Одноразовая настройка

```bash
# из worktree production
git remote add prod root@152.228.118.128:/root/git/crm-production.git
```

Скрипт сам добавит remote `prod`, если его нет.

## Быстрый деплой

```bash
cd inteli-dev-crm-production
DEPLOY_SSH_PASSWORD='...' ../inteli-dev-crm/scripts/deploy-prod.sh
```

Скрипт `scripts/deploy-prod.sh`:

1. проверяет, что текущая ветка — `production` и дерево чистое;
2. пушит ветку в `/root/git/crm-production.git` на сервере (GitHub-ключ недоступен);
3. кладёт `git archive` ветки поверх `/www/wwwroot/crm.inteli-dev.ru`
   — `.env`, `vendor`, `storage`, `public/build` и `docker-compose.aapanel.yml`
   не входят в архив и сохраняются;
4. собирает образ `app` на сервере (там есть сеть до packagist/yoomoney);
5. перезапускает контейнер, применяет миграции и чистит кэши;
6. проверяет `http://127.0.0.1:18001/up`.

Если настроен SSH-ключ, пароль не нужен — просто `scripts/deploy-prod.sh`.
Флаг `DEPLOY_SKIP_BUILD=1` пропускает сборку (только код и миграции).

## Ручной деплой

```bash
git archive --format=tar.gz production | ssh root@152.228.118.128 \
  "tar xzf - -C /www/wwwroot/crm.inteli-dev.ru"
ssh root@152.228.118.128 "cd /www/wwwroot/crm.inteli-dev.ru && \
  docker compose -f docker-compose.yml -f docker-compose.aapanel.yml build app && \
  docker compose -f docker-compose.yml -f docker-compose.aapanel.yml up -d app && \
  docker compose -f docker-compose.yml -f docker-compose.aapanel.yml exec -T app \
    php artisan migrate --force"
```

## Миграции

Entrypoint контейнера `app` сам выполняет `php artisan migrate --force` при старте.
Ручной запуск:

```bash
ssh root@152.228.118.128 "cd /www/wwwroot/crm.inteli-dev.ru && \
  docker compose -f docker-compose.yml -f docker-compose.aapanel.yml exec -T app \
  php artisan migrate --force"
```

## Важно

- GitHub (`origin`) с этой машины недоступен по ключу; резервная копия истории —
  bare-репозиторий на сервере `/root/git/crm-production.git`.
- Не выполнять `rsync --delete` и `git clean` в рабочем каталоге: там лежат
  `.env` → `.env.production`, `vendor`, `storage` и aaPanel-конфиг.
- Пароль root не хранить в репозитории: передавайте через `DEPLOY_SSH_PASSWORD`.
