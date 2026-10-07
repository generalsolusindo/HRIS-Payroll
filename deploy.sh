#!/usr/bin/env bash
#
# Pulls the latest main and brings this server's copy of the app fully up
# to date: dependencies, migrations, built frontend assets, caches, and a
# php-fpm restart. Run this after every `git pull` on a deploy target —
# never pull by hand and reconstruct the rest of these steps from memory,
# that's exactly what left Wayfinder unable to find newly pulled
# controllers the last time this was done manually.
#
# Usage: ./deploy.sh [branch]   (branch defaults to main)

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

BRANCH="${1:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.5-fpm}"

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }

# Always try to bring the app back out of maintenance mode, even if a
# later step fails — a half-finished deploy must never leave the site
# stuck behind the maintenance page with no way back in.
cleanup() { php artisan up >/dev/null 2>&1 || true; }
trap cleanup EXIT

log "Checking for local changes before pulling"
if [[ -n "$(git status --porcelain)" ]]; then
    stash_message="deploy.sh auto-stash $(date '+%Y-%m-%d %H:%M:%S')"
    echo "Local changes found — stashing them as: $stash_message"
    echo "(run 'git stash list' / 'git stash pop' afterwards if you need them back)"
    git stash push -u -m "$stash_message"
fi

log "Pulling origin/$BRANCH"
git pull origin "$BRANCH"

log "Putting the app into maintenance mode"
php artisan down --render="errors::503" || true

log "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader

log "Running database migrations"
php artisan migrate --force

log "Installing JS dependencies"
npm ci

log "Building frontend assets"
# Must run after composer install: Wayfinder generates resources/js/actions
# and resources/js/routes by reflecting on the app's own PHP controllers —
# if a controller added in this pull isn't autoloadable yet, its action
# file silently never gets generated and the build fails with
# "Could not load .../SomeController".
npm run build

log "Refreshing caches"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Restarting php-fpm ($PHP_FPM_SERVICE)"
sudo systemctl restart "$PHP_FPM_SERVICE"

log "Done — app is back up"
# cleanup() (php artisan up) still runs via the EXIT trap above.
