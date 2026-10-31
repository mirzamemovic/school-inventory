# The one-day video demo only: see VIDEO-DEMO.md. The school's real deployment
# is DEPLOYMENT.md and uses none of this.

FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-scripts --optimize-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# After composer, because Tailwind also reads Laravel's pagination views in vendor.
FROM node:24-alpine AS assets
WORKDIR /app
COPY --from=vendor /app ./
RUN npm ci && npm run build

# PHP 8.3 is what the test suite runs on.
FROM php:8.3-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd opcache \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite setenvif \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build
COPY docker/php.ini $PHP_INI_DIR/conf.d/school-inventory.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# The demo data is built into the image, so every restart begins from the same
# clean state and no database file is uploaded from anyone's computer. It runs
# here as non-production on purpose: DemoSeeder refuses production, and the
# running site below is production.
RUN sed -i 's/\r$//' docker/start.sh \
    && echo 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && touch database/database.sqlite \
    && env APP_ENV=local APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" DB_CONNECTION=sqlite \
        CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync LOG_CHANNEL=stderr SCHOOL_TAG_PREFIX=INV \
        sh -c 'php artisan package:discover --ansi \
            && php artisan migrate --force --seed \
            && php artisan db:seed --class=DemoSeeder --force' \
    && chown -R www-data:www-data database storage bootstrap/cache

ENV APP_NAME="School Inventory" \
    APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    LOG_CHANNEL=stderr \
    MAIL_MAILER=log \
    SCHOOL_TAG_PREFIX=INV

EXPOSE 8080

CMD ["sh", "docker/start.sh"]
