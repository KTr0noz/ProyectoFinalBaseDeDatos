# ============================================================================
# ARCHIVO: Dockerfile
# DESCRIPCIÓN:
#   Imagen oficial de PHP-FPM (Alpine Linux) con servidor proxy Nginx y
#   extensiones de PostgreSQL (pdo, pdo_pgsql, pgsql), lista para Railway.
# ============================================================================

FROM php:fpm-alpine

# 1. Instalar Nginx, gettext (para envsubst) y librerías de PostgreSQL
RUN apk add --no-cache \
    nginx \
    gettext \
    libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql \
    && rm -rf /var/cache/apk/*

# 2. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Definir directorio de trabajo
WORKDIR /var/www/html

# 4. Copiar la plantilla de configuración de Nginx y el script de arranque
COPY nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

# 5. Copiar el código de la aplicación y ajustar permisos
COPY . /var/www/html
RUN rm -f /var/www/html/Dockerfile /var/www/html/nginx.conf.template /var/www/html/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && mkdir -p /run/nginx

# 6. Asegurar saltos de línea Unix (LF) y permisos de ejecución en el entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# 7. Variables de entorno por defecto (Railway puede sobrescribirlas desde el panel)
ENV PORT=8080
ENV DATABASE_URL="postgresql://postgres:bHSXhbjGUCeYNCyjIvdyRcXNckEJTRPZ@ballast.proxy.rlwy.net:49477/railway"

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
