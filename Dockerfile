# Stage 1: build frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install --ignore-scripts
COPY . .
RUN npm run build

# Stage 2: production image
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    python3 \
    python3-pip \
    sqlite3 \
    libsqlite3-dev \
    libonig-dev \
    zip \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_sqlite bcmath mbstring opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY scripts/requirements.txt /tmp/requirements.txt
RUN pip3 install --break-system-packages --no-cache-dir -r /tmp/requirements.txt

WORKDIR /var/www/html

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}

COPY --chown=www-data:www-data . .
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN mkdir -p storage/{app/public,framework/{cache/data,sessions,testing,views},logs} \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
