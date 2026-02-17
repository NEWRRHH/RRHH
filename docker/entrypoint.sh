#!/bin/sh
set -e

# runtime entrypoint for dev: fix permissions and ensure composer deps
echo "[entrypoint] fixing permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# install composer dependencies if missing (keeps dev startup tolerant)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
  echo "[entrypoint] vendor not found — running composer install (this may take a while)..."
  composer install --prefer-dist --no-interaction --no-progress || true
fi

# run the original CMD (php-fpm)
exec "$@"