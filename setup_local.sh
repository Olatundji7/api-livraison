#!/usr/bin/env bash
set -euo pipefail

# MA Livraison - installation locale MySQL
# Pré-requis : PHP 8.3+, Composer, MySQL, extension PHP MySQL.

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate
  echo "`.env` créé. Vérifiez DB_* et FEDAPAY_* avant de continuer."
fi

mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public
chmod -R ug+rwX bootstrap/cache storage || true

php artisan migrate --force
php artisan storage:link || true
php artisan route:list --path=api --except-vendor >/dev/null

echo "Installation API terminée. Lancez : php artisan serve --host=127.0.0.1 --port=8000"
