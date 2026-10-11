FROM php:8.2-fpm-bullseye

# Install system utilities, nginx, and mysql client
RUN apt-get update && apt-get install -y \
    nginx \
    default-mysql-client \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application code
COPY . .

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure Nginx site
RUN cp nginx.conf /etc/nginx/sites-available/default 2>/dev/null || true

# Prepare storage and permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy entrypoint runner
RUN chmod +x bin/render-build.sh

EXPOSE 80

# Start php-fpm and nginx
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    service nginx start && \
    php-fpm -F