FROM php:8.2-fpm-alpine

# Install Nginx, system utilities, and official extension helper
RUN apk add --no-cache \
    nginx \
    mysql-client \
    bash \
    curl \
    git \
    zip \
    unzip \
    icu-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev

# Use official script to install all needed PHP extensions cleanly
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql mbstring exif pcntl bcmath gd intl zip opcache

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application code
COPY . .

# Install production dependencies without dev packages
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure Nginx for Laravel
RUN mkdir -p /run/nginx && \
    cp nginx.conf /etc/nginx/http.d/default.conf 2>/dev/null || true

# Fix storage permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

# Start php-fpm and nginx together on container boot
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    nginx && \
    php-fpm -F