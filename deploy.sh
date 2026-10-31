#!/bin/bash

# ============================================================
# deploy.sh — Push ke GitHub & Pull di Server
# Usage:
#   ./deploy.sh                    → commit + push ke main & dev
#   ./deploy.sh "pesan commit"     → commit dengan pesan kustom
#   ./deploy.sh --server-only      → hanya pull di server
# ============================================================

set -e

# Warna output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log()    { echo -e "${GREEN}[✔]${NC} $1"; }
warn()   { echo -e "${YELLOW}[!]${NC} $1"; }
error()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }
step()   { echo -e "\n${BLUE}══════════════════════════════════════${NC}"; echo -e "${BLUE}  $1${NC}"; echo -e "${BLUE}══════════════════════════════════════${NC}"; }

# ─── Konfigurasi ───────────────────────────────────────────
SERVER_USER="qs"
SERVER_HOST="172.16.10.127"
SERVER_PATH="~/HRIS-Payroll"
REMOTE="origin"
BRANCHES=("main" "dev")

COMMIT_MSG="${1:-"fix: update validasi payroll period & izin setengah hari"}"

# ─── Jika hanya pull server ────────────────────────────────
if [[ "$1" == "--server-only" ]]; then
    step "Pull di Server ($SERVER_HOST)"
    ssh ${SERVER_USER}@${SERVER_HOST} "
        cd ${SERVER_PATH}
        echo '--- git pull main ---'
        git pull origin main
        echo '--- composer install ---'
        composer install --no-dev --optimize-autoloader
        echo '--- php artisan migrate ---'
        php artisan migrate --force
        echo '--- php artisan optimize ---'
        php artisan optimize
        echo '--- Done ---'
    "
    log "Server berhasil diperbarui!"
    exit 0
fi

# ─── 1. Commit semua perubahan ─────────────────────────────
step "1. Git Add & Commit"

git add \
    app/Http/Requests/Hrd/StorePayrollPeriodRequest.php \
    app/Http/Requests/Hrd/UpdatePayrollPeriodRequest.php \
    resources/js/pages/izin-detail.tsx \
    database/migrations/2026_10_31_110153_drop_payroll_period_dates_unique_constraint.php

git status --short

echo ""
read -p "Commit dengan pesan: \"${COMMIT_MSG}\" ? (y/n): " CONFIRM
if [[ "$CONFIRM" != "y" && "$CONFIRM" != "Y" ]]; then
    warn "Dibatalkan."
    exit 0
fi

git commit -m "${COMMIT_MSG}"
log "Commit berhasil."

# ─── 2. Push ke main ───────────────────────────────────────
step "2. Push ke branch: main"
git push ${REMOTE} main
log "Push ke main selesai."

# ─── 3. Merge main → dev & push ────────────────────────────
step "3. Push ke branch: dev"
git checkout dev
git merge main --no-edit
git push ${REMOTE} dev
git checkout main
log "Push ke dev selesai."

# ─── 4. Pull di Server ─────────────────────────────────────
step "4. Deploy ke Server ($SERVER_HOST)"
ssh ${SERVER_USER}@${SERVER_HOST} "
    cd ${SERVER_PATH}
    echo '--- git pull origin main ---'
    git pull origin main
    echo '--- composer install ---'
    composer install --no-dev --optimize-autoloader
    echo '--- php artisan migrate ---'
    php artisan migrate --force
    echo '--- php artisan optimize:clear ---'
    php artisan optimize:clear
    echo '--- php artisan optimize ---'
    php artisan optimize
    echo ''
    echo '✔ Deploy selesai!'
"

log "============================================="
log " DEPLOY SELESAI!"
log " main  ✔  dev  ✔  server  ✔"
log "============================================="
