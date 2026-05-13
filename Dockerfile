# Stage 1: Build frontend assets
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run prod

# Stage 2: PHP application
FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Apache MPM - disable mpm_event, enable mpm_prefork (required for mod_php)
RUN a2dismod mpm_event 2>/dev/null || true \
    && a2enmod mpm_prefork 2>/dev/null || true

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache to use /var/www/html/public as document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy built frontend assets from node builder
COPY --from=node-builder /app/public/assets ./public/assets
COPY --from=node-builder /app/public/mix-manifest.json ./public/mix-manifest.json

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Ensure required storage directories exist (not tracked in git / excluded by .dockerignore)
RUN mkdir -p /var/www/html/storage/framework/views \
             /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create startup script that dynamically sets the PORT
COPY <<'EOF' /usr/local/bin/start.sh
#!/bin/bash
set -e

# Railway provides PORT env var - configure Apache to listen on it
PORT=${PORT:-80}
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ensure critical env defaults for Railway
export APP_URL="${APP_URL:-https://playstationrental-production.up.railway.app}"
export TIMEZONE="${TIMEZONE:-Asia/Jakarta}"

# Run Laravel setup
php artisan config:clear
php artisan config:cache || true
php artisan migrate --force || true
php artisan storage:link || true
php artisan permission:cache-reset || true
php artisan db:seed --force && echo "Seeder OK" || echo "Seeder FAILED (see above)"
php artisan permission:cache-reset || true
php artisan route:cache || true
php artisan view:cache || true

# Start Apache
exec apache2-foreground
EOF
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
