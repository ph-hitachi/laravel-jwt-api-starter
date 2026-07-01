#!/bin/sh
set -e

# If /var/www/html does not contain Laravel (artisan), install it into the mounted directory
if [ ! -f /var/www/html/artisan ]; then
  echo "Detected empty /var/www/html — installing Laravel into /var/www/html"
  # allow Composer more time
  export COMPOSER_PROCESS_TIMEOUT=2000
  composer create-project --no-progress --prefer-dist laravel/laravel /var/www/html || true
  # ensure permissions (may vary on host)
  if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data /var/www/html || true
  fi
fi

exec "$@"
