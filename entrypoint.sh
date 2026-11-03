#!/bin/sh
set -e

# Railway inyecta la variable $PORT automáticamente (por defecto usamos 8080)
export PORT=${PORT:-8080}

# Reemplazar ${PORT} en la plantilla de Nginx
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

echo "Iniciando PHP-FPM..."
php-fpm -D

echo "Iniciando Nginx escuchando en el puerto ${PORT}..."
exec nginx -g "daemon off;"
