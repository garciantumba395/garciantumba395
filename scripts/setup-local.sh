#!/usr/bin/env bash
# Installation locale idempotente : dépendances, .env, bases PostgreSQL, migrations, assets.
# Usage : bash scripts/setup-local.sh   (sudo requis seulement pour créer le rôle/les bases PostgreSQL)
set -euo pipefail
cd "$(dirname "$0")/.."

command -v php >/dev/null && command -v composer >/dev/null || { echo "PHP et Composer requis"; exit 1; }
composer install --no-interaction --prefer-dist

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

# Mot de passe généré aléatoirement, écrit uniquement dans .env (jamais versionné).
if ! grep -q '^DB_PASSWORD=.\+' .env; then
  PW="$(openssl rand -hex 16)"
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${PW}|" .env
  run() { if [ "$(id -u)" -eq 0 ]; then su postgres -c "psql -tAc \"$1\""; else sudo -u postgres psql -tAc "$1"; fi; }
  run "DO \$\$ BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='sms') THEN CREATE ROLE sms LOGIN CREATEDB; END IF; END \$\$;"
  run "ALTER ROLE sms PASSWORD '${PW}';"
  for db in sms_saas sms_saas_test; do
    [ "$(run "SELECT 1 FROM pg_database WHERE datname='${db}'")" = "1" ] || run "CREATE DATABASE ${db} OWNER sms;"
  done
fi

php artisan migrate --force
npm install --no-audit --no-fund
npm run build
echo "OK. Lancer : php artisan serve   |  Tests : php artisan test"
