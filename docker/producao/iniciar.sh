#!/bin/sh
# Prepara o Laravel a cada início do container e depois roda o comando (site ou agendador).
set -e
cd /app

# O volume de storage começa vazio na primeira vez: recria as pastas que o Laravel usa.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RODAR_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
fi

exec "$@"
