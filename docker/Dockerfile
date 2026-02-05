# Stage 1: Build Assets
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP Environment
FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apk add --no-cache \
    nginx \
    oniguruma-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    icu-dev \
    gettext-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    soap \
    calendar \
    gettext \
    opcache

# Get composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .
# Copy assets from Stage 1
COPY --from=assets /app/public/build ./public/build

# Install PHP dependencies
RUN COMPOSER_MEMORY_LIMIT=-1 composer update --no-dev --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs

# Configuration
RUN sed -i 's/;clear_env = no/clear_env = no/g' /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Permissions
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Expose port 80
EXPOSE 80

# Entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
