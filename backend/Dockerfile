# syntax=docker/dockerfile:1.7
# Imagen única de la API: Nginx + PHP-FPM + trabajador del outbox + programador
# Se publica en Amazon ECR y la ejecuta Amazon ECS Express Mode sobre Fargate (puerto 8080)

# ---------- 1. Dependencias de PHP (Composer) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
# --ignore-platform-reqs: la imagen de Composer no trae gd ni pdo_pgsql; las extensiones se instalan en la etapa 2
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- 2. Imagen de ejecución ----------
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache nginx supervisor libpq libpng libjpeg-turbo libwebp freetype icu-libs libzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev icu-dev libzip-dev linux-headers \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd intl zip opcache bcmath pcntl \
 && apk del .build-deps \
 && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-funeraria.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-funeraria.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
# Interfaz compilada (se copia a backend/web antes del build
COPY --from=vendor --chown=www-data:www-data /app/web /var/www/web

RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /run/nginx \
 && chown -R www-data:www-data storage bootstrap/cache /run/nginx /var/lib/nginx /var/log/nginx

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD wget -qO- http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
