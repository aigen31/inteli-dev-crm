#!/usr/bin/env bash
set -euo pipefail

# ─── Configuration ───────────────────────────────────────────────
REMOTE_HOST="crm-prod"
REMOTE_DIR="/home/outline_kz/crm"
LOCAL_DIR="$(cd "$(dirname "$0")" && pwd)"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${CYAN}[deploy]${NC} $1"; }
ok()   { echo -e "${GREEN}[  ok  ]${NC} $1"; }
warn() { echo -e "${YELLOW}[ warn ]${NC} $1"; }
fail() { echo -e "${RED}[FAIL!]${NC} $1"; exit 1; }

# ─── Pre-flight checks ──────────────────────────────────────────
log "Pre-flight checks..."

ssh -o BatchMode=yes -o ConnectTimeout=5 "$REMOTE_HOST" 'echo ok' >/dev/null 2>&1 \
    || fail "Cannot connect to $REMOTE_HOST. Check SSH config."

# Ensure .env.production exists locally
[[ -f "$LOCAL_DIR/.env.production" ]] \
    || fail ".env.production not found. Copy .env.example and configure for production."

ok "Pre-flight passed"

# ─── Step 1: Sync project files ─────────────────────────────────
log "Syncing project files to $REMOTE_HOST:$REMOTE_DIR ..."

rsync -azh --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/data/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='storage/app/attachments/*' \
    --exclude='bootstrap/cache/*' \
    --exclude='public/build' \
    --exclude='public/hot' \
    --exclude='public/storage' \
    --exclude='tests' \
    --exclude='.phpunit.cache' \
    --exclude='docker-compose.override.yml' \
    --exclude='.idea' \
    --exclude='.vscode' \
    --exclude='*.md' \
    --exclude='.github' \
    "$LOCAL_DIR/" "$REMOTE_HOST:$REMOTE_DIR/"

ok "Files synced"

# ─── Step 2: Upload production .env (only if changed or missing) ─
log "Syncing .env.production → .env ..."

rsync -azh "$LOCAL_DIR/.env.production" "$REMOTE_HOST:$REMOTE_DIR/.env"

ok ".env deployed"

# ─── Step 3: Build & deploy on server ───────────────────────────
log "Building and deploying on server..."

ssh "$REMOTE_HOST" bash -s "$REMOTE_DIR" << 'REMOTE_SCRIPT'
set -euo pipefail
REMOTE_DIR="$1"
cd "$REMOTE_DIR"

echo "[remote] Building Docker images..."
docker compose build --parallel 2>&1 | tail -5

echo "[remote] Starting services (rolling update)..."
docker compose up -d --remove-orphans --wait 2>&1 | tail -10

echo "[remote] Checking container health..."
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

echo "[remote] Cleaning up old images..."
docker image prune -f 2>&1 | tail -2

echo "[remote] Deploy complete!"
REMOTE_SCRIPT

ok "Deployment finished!"
echo ""
log "Application available at: $(grep APP_URL "$LOCAL_DIR/.env.production" | cut -d= -f2-)"
