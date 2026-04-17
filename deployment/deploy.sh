#!/usr/bin/env bash
# IRMS zero-downtime deploy script.
# Assumes a capistrano-style layout:
#   /var/www/irms/
#     releases/<timestamp>/
#     shared/.env
#     shared/storage/
#     current -> releases/<latest>
#
# Usage on the VPS (run as deploy user):
#   cd /var/www/irms
#   ./deploy.sh git@github.com:org/irms.git main
#
# Prerequisites: git, php 8.2+, composer, node 20+, npm, redis, postgres, supervisor.

set -euo pipefail

REPO_URL="${1:-}"
BRANCH="${2:-main}"
APP_ROOT="/var/www/irms"
SHARED="${APP_ROOT}/shared"
RELEASES="${APP_ROOT}/releases"
KEEP_RELEASES=5

if [[ -z "${REPO_URL}" ]]; then
    echo "Usage: $0 <git-url> [branch]" >&2
    exit 1
fi

TIMESTAMP="$(date -u +%Y%m%d%H%M%S)"
NEW_RELEASE="${RELEASES}/${TIMESTAMP}"

echo "==> Cloning ${REPO_URL}@${BRANCH} into ${NEW_RELEASE}"
mkdir -p "${RELEASES}"
git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${NEW_RELEASE}"
cd "${NEW_RELEASE}"

echo "==> Linking shared files"
rm -rf storage
ln -s "${SHARED}/storage" storage
ln -sf "${SHARED}/.env" .env

echo "==> Installing PHP dependencies"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "==> Installing Node dependencies & building assets"
npm ci
npm run build

echo "==> Building citizen reporting app"
if [[ -d report-app ]]; then
    pushd report-app >/dev/null
    npm ci
    npm run build
    popd >/dev/null
    rm -rf public/citizen
    ln -sfn ../report-app/dist public/citizen
else
    echo "(skipping — report-app/ not found)"
fi

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Warming caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Switching current symlink"
ln -sfn "${NEW_RELEASE}" "${APP_ROOT}/current"

echo "==> Reloading PHP-FPM"
sudo systemctl reload php8.4-fpm || true

echo "==> Signaling Horizon to restart workers"
php artisan horizon:terminate || true

echo "==> Signaling Reverb to restart (supervisor)"
sudo supervisorctl restart irms-reverb || true

echo "==> Pruning old releases (keeping last ${KEEP_RELEASES})"
ls -1dt "${RELEASES}"/*/ | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "==> Deploy complete: ${TIMESTAMP}"
