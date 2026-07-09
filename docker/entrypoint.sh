#!/bin/sh
set -e

php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec frankenphp run --config /etc/caddy/Caddyfile
