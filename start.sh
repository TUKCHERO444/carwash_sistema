#!/usr/bin/env bash
set -e

echo "=> Asegurando permisos de almacenamiento..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "=> Limpiando cachés antiguas..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "=> Creando caché optimizada para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=> Ejecutando migraciones de la base de datos (PostgreSQL)..."
php artisan migrate --force

echo "=> Iniciando servidor Apache..."
exec apache2-foreground
