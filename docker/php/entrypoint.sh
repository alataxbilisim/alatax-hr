#!/bin/sh
set -e

cd /var/www/html

# Volume mount sonrası vendor yoksa kur
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] composer install..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Yazılabilir dizinler (root ile başlatılıp sonra www-data'ya geçilebilir;
# image USER www-data ise chown atlanır)
if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
fi

exec "$@"
