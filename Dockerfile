FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev \
    libpng-dev libjpeg-dev libwebp-dev \
    libxml2-dev libonig-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo pdo_mysql mysqli \
    mbstring xml zip bcmath \
    gd opcache pcntl intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy application files
COPY . .

# Create storage structure and set permissions
RUN mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/framework/testing \
    && mkdir -p storage/logs \
    && mkdir -p storage/app/public \
    && mkdir -p bootstrap/cache \
    && chmod -R 777 storage \
    && chmod -R 777 bootstrap/cache

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Make entrypoint executable
RUN chmod +x docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/var/www/docker-entrypoint.sh"]
