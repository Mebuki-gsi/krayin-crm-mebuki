FROM php:8.2-cli

# Run as root to avoid permission issues
USER root

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

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Make entrypoint executable
RUN chmod +x docker-entrypoint.sh

EXPOSE 8000

# Run as root to ensure write permissions
ENTRYPOINT ["/var/www/docker-entrypoint.sh"]
