# PHP Composer Builder
FROM composer:2 AS composer_builder
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev)
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist

# Copy full project
COPY . .

# Optimize autoloader
RUN composer install --no-dev --optimize-autoloader --no-interaction


# ---------------------------
# Production Image
# ---------------------------
FROM php:8.2-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip git curl libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

WORKDIR /var/www

# Copy app from builder
COPY --from=composer_builder /app /var/www

# Permissions for Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
