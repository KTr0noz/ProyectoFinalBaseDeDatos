# Usamos la última versión oficial de PHP con FPM sobre Alpine Linux
FROM php:fpm-alpine

# Instalar Nginx, gettext (para envsubst) y librerías de PostgreSQL
RUN apk add --no-cache \
    nginx \
    gettext \
    libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql \
    && rm -rf /var/cache/apk/*

# Instalar Composer (opcional pero recomendado para proyectos PHP modernos)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar la plantilla de configuración de Nginx y el script de inicio
COPY nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

# Copiar el código del proyecto al contenedor y limpiar archivos de infraestructura del webroot
COPY . /var/www/html
RUN rm -f /var/www/html/Dockerfile /var/www/html/nginx.conf.template /var/www/html/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && mkdir -p /run/nginx

# Asegurar formato de línea Unix (LF) por si se sube desde Windows y dar permisos de ejecución
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# Variables de entorno por defecto (Railway puede sobrescribirlas desde el panel)
ENV PORT=8080
ENV DATABASE_URL="postgresql://postgres:bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ@ballast.proxy.rlwy.net:49477/railway"

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
