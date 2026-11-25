#!/bin/sh
# Prepara la aplicación al arrancar el contenedor. Los secretos llegan como
# variables de entorno (ECS los inyecta desde AWS Secrets Manager al iniciar la tarea)
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "Falta APP_KEY: definir en Secrets Manager (php artisan key:generate --show)." >&2
  exit 1
fi

# Cachés de configuración, rutas y vistas (arranque más rápido)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true

# Migraciones
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  php artisan db:seed --class=Database\\Seeders\\CatalogoBaseSeeder --force
fi

# Primer administrador (solo si se definieron las variables; no hace nada si ya existe).
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_RUT:-}" ] && [ -n "${ADMIN_PASSWORD_INICIAL:-}" ]; then
  php artisan funeraria:crear-admin "$ADMIN_EMAIL" "${ADMIN_NOMBRE:-Administrador}" "$ADMIN_RUT" --password-env=ADMIN_PASSWORD_INICIAL --si-no-existe
fi

exec "$@"
