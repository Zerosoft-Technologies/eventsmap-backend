# PHP Composer Builder
FROM composer:2 AS composer_builder
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev)
# First update lock file to include new packages (skip scripts to avoid artisan error)
RUN composer update laravel/sanctum --no-interaction --no-progress --prefer-dist --no-scripts
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist

# Copy full project
COPY . .

# Optimize autoloader
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Regenerate package manifest without dev dependencies
RUN composer dump-autoload --no-dev --optimize


# ---------------------------
# Production Image
# ---------------------------
FROM php:8.4-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip git curl libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    pdo_pgsql pgsql

WORKDIR /var/www

# Copy app from builder
COPY --from=composer_builder /app /var/www

# Create storage link and set permissions
RUN php artisan storage:link \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/public/storage

# Remove cached files that reference development dependencies
RUN rm -f /var/www/bootstrap/cache/services.php \
       && rm -f /var/www/bootstrap/cache/packages.php \
       && rm -f /var/www/bootstrap/cache/config.php

# Clear Laravel caches (without database connection)
RUN php artisan config:clear && php artisan clear-compiled

# Install Sanctum (automatically installed via composer, just ensure it's published)
RUN php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --force || true

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
