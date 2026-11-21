# Dockerfile for Laravel 11 + React + TypeScript + Inertia
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    postgresql-dev \
    mariadb-dev \
    nodejs \
    npm \
    redis \
    autoconf \
    gcc \
    g++ \
    make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    bcmath \
    gd \
    intl \
    zip \
    exif \
    pcntl \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-progress

# Copy package files for caching
COPY package.json package-lock.json* ./

# Install Node dependencies
RUN npm ci --legacy-peer-deps

# Copy application files
COPY . .

# Build frontend assets
RUN npm run build

# Generate app key
RUN php artisan key:generate --force

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy nginx and supervisor configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configure PHP-FPM pool to use the correct socket and create log directory
RUN sed -i 's/^;listen = 127.0.0.1:9000/listen = \/var\/run\/php\/php8.4-fpm.sock/' /usr/local/etc/php-fpm.d/www.conf \
    && mkdir -p /var/run/php /usr/local/var/log \
    && chown www-data:www-data /var/run/php /usr/local/var/log

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]