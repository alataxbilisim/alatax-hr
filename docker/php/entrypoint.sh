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

# public/storage -> storage/app/public (idempotent). Yalnız php-fpm (app) servisi oluşturur;
# queue/scheduler aynı volume'u paylaşır ve aynı anda başlar.
if [ "$1" = "php-fpm" ] && [ ! -L public/storage ] && [ ! -e public/storage ]; then
  echo "[entrypoint] storage:link..."
  php artisan storage:link --no-interaction || echo "[entrypoint] storage:link başarısız; uygulama yine de başlatılıyor"
fi

exec "$@"
