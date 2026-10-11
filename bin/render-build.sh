#!/usr/bin/env bash
# Exit immediately if a command exits with a non-zero status
set -e

echo "==> Running production build pipeline..."

# 1. Install production Composer dependencies (exclude dev, optimize classmap)
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 2. Cache Laravel configurations, routing tables, and Blade templates
echo "==> Caching application configurations and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Run database migrations safely
echo "==> Executing database migrations..."
php artisan migrate --force

echo "==> Build complete."
