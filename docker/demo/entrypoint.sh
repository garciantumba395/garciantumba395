#!/usr/bin/env bash
# Démarre le rôle demandé (web, worker, scheduler). Le rôle « web » prépare la base et les données fictives.
set -euo pipefail
cd /app
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
role="${1:-web}"

until php -r 'new PDO("pgsql:host=".getenv("DB_HOST").";port=5432;dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do echo "En attente de PostgreSQL…"; sleep 2; done

case "$role" in
  web)
    php artisan migrate --force
    n="$(php -r '$p=new PDO("pgsql:host=".getenv("DB_HOST").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo $p->query("select count(*) from users")->fetchColumn();')"
    if [ "$n" = "0" ]; then php artisan db:seed --class=DemoSeeder --force; fi
    exec php artisan serve --host=0.0.0.0 --port=8000 ;;
  worker)    exec php artisan queue:work --queue=sms,default --sleep=1 --tries=1 ;;
  scheduler) exec php artisan schedule:work ;;
  *) echo "Rôle inconnu : $role"; exit 1 ;;
esac
