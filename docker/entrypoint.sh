#!/bin/sh
set -e

# Render assigne dynamiquement le port d'écoute via $PORT
: "${PORT:=80}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf

# APP_KEY, DB_*, etc. sont fournis par les variables d'environnement Render (pas de .env en prod)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --force

exec "$@"
