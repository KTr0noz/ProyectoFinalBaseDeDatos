#!/bin/sh
set -e

# Railway inyecta la variable $PORT automáticamente (por defecto usamos 8080)
export PORT=${PORT:-8080}

# Reemplazar ${PORT} en la plantilla de Nginx
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    echo "Verificando cuenta de administrador por defecto..."
    php /var/www/html/scripts/crear_admin.php || echo "Aviso: no se pudo sincronizar el administrador al iniciar."
fi

echo "Iniciando PHP-FPM..."
php-fpm -D

echo "Iniciando Nginx escuchando en el puerto ${PORT}..."
exec nginx -g "daemon off;"
