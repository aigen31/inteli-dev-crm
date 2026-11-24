#!/usr/bin/env bash
# Деплой production-ветки CRM на прод-сервер через SSH.
#
# Что делает:
#   1. проверяет чистоту дерева и ветку production;
#   2. пушит ветку в bare-репозиторий на сервере (origin недоступен — GitHub-ключ);
#   3. отправляет git-архив ветки поверх /www/wwwroot/crm.inteli-dev.ru
#      (файлы, которых нет в ветке — .env, vendor, storage, aaPanel-конфиг — сохраняются);
#   4. собирает образ app на сервере и перезапускает контейнер;
#   5. применяет миграции и чистит кэши;
#   6. проверяет /up.
#
# Использование:
#   DEPLOY_SSH_PASSWORD='...' scripts/deploy-prod.sh
#   # или с ключом:
#   scripts/deploy-prod.sh
#
# Переменные:
#   DEPLOY_HOST           root@152.228.118.128
#   DEPLOY_APP_DIR        /www/wwwroot/crm.inteli-dev.ru
#   DEPLOY_BRANCH         production
#   DEPLOY_SSH_PASSWORD   пароль root (если нет ключа)
#   DEPLOY_SKIP_BUILD=1   не собирать образ (только код + миграции)
set -euo pipefail

HOST="${DEPLOY_HOST:-root@152.228.118.128}"
APP_DIR="${DEPLOY_APP_DIR:-/www/wwwroot/crm.inteli-dev.ru}"
BRANCH="${DEPLOY_BRANCH:-production}"
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.aapanel.yml"

cd "$(git rev-parse --show-toplevel)"

current_branch=$(git rev-parse --abbrev-ref HEAD)
[ "$current_branch" = "$BRANCH" ] || { echo "Нужна ветка $BRANCH, сейчас $current_branch"; exit 1; }
[ -z "$(git status --porcelain)" ] || { echo "Дерево грязное: закоммитьте изменения"; exit 1; }

SSH_OPTS=(-F /dev/null -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR)
ASKPASS=""
cleanup() { [ -n "$ASKPASS" ] && rm -f "$ASKPASS" "$ASKPASS.ssh"; }
trap cleanup EXIT

if [ -n "${DEPLOY_SSH_PASSWORD:-}" ]; then
    ASKPASS=$(mktemp)
    printf '#!/bin/sh\nprintf "%%s\\n" %q\n' "$DEPLOY_SSH_PASSWORD" > "$ASKPASS"
    chmod 700 "$ASKPASS"
    printf '#!/bin/sh\nexport SSH_ASKPASS=%s\nexport SSH_ASKPASS_REQUIRE=force\nexec setsid -w ssh %s -o PreferredAuthentications=password -o PubkeyAuthentication=no "$@"\n' "$ASKPASS" "${SSH_OPTS[*]}" > "$ASKPASS.ssh"
    chmod 700 "$ASKPASS.ssh"
    SSH_CMD=(setsid -w env SSH_ASKPASS="$ASKPASS" SSH_ASKPASS_REQUIRE=force ssh "${SSH_OPTS[@]}" -o PreferredAuthentications=password -o PubkeyAuthentication=no)
    GIT_SSH="$ASKPASS.ssh"
else
    SSH_CMD=(ssh "${SSH_OPTS[@]}")
    GIT_SSH="ssh ${SSH_OPTS[*]}"
fi

echo "→ push $BRANCH в prod-репозиторий"
if ! git remote get-url prod >/dev/null 2>&1; then
    git remote add prod "$HOST:/root/git/crm-production.git"
fi
GIT_SSH_COMMAND="$GIT_SSH" git push prod "$BRANCH"

if [ "${DEPLOY_PUSH_ORIGIN:-1}" = "1" ]; then
    echo "→ push $BRANCH в origin (GitHub)"
    if git push origin "$BRANCH"; then
        echo "  origin обновлён"
    else
        echo "  ⚠ origin не обновлён: нужен ssh-agent с ключом (ssh-add ~/.ssh/id_ed25519)"
    fi
fi

echo "→ отправка файлов в $APP_DIR"
git archive --format=tar.gz "$BRANCH" | "${SSH_CMD[@]}" "$HOST" "tar xzf - -C '$APP_DIR'"

echo "→ сборка и перезапуск на сервере"
if [ "${DEPLOY_SKIP_BUILD:-0}" = "1" ]; then
    "${SSH_CMD[@]}" "$HOST" "set -e; cd '$APP_DIR'; $COMPOSE up -d app; $COMPOSE exec -T app php artisan migrate --force; $COMPOSE exec -T app php artisan optimize:clear"
else
    "${SSH_CMD[@]}" "$HOST" "set -e; cd '$APP_DIR'; $COMPOSE build app; $COMPOSE up -d app; $COMPOSE exec -T app php artisan migrate --force; $COMPOSE exec -T app php artisan optimize:clear"
fi

echo "→ проверка /up"
"${SSH_CMD[@]}" "$HOST" 'for i in $(seq 1 30); do code=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:18001/up); [ "$code" = "200" ] && { echo 200; exit 0; }; sleep 2; done; echo "$code"; exit 1'
