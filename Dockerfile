# Use PHP with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip sqlite3 && \
    docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd

# Enable Apache rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Ensure storage & cache are writable
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Create sqlite file if missing
RUN mkdir -p /var/data && touch /var/data/database.sqlite

# Expose Render port
EXPOSE 10000

# Run migrations and start Laravel
CMD php artisan migrate --force && php artisan db:seed --class=UserSeeder && php artisan serve --host 0.0.0.0 --port 10000

