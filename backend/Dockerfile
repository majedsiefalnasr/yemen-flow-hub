FROM composer:2 AS vendor

WORKDIR /var/www/html
ENV APP_ENV=local
COPY . .
# gd is required by mpdf/mpdf at install-time platform check
RUN apk add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM php:8.3-cli-alpine AS runtime

WORKDIR /var/www/html

RUN apk add --no-cache bash freetype-dev icu-dev libjpeg-turbo-dev libpng-dev libzip-dev oniguruma-dev unzip $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl mbstring pcntl pdo_mysql zip

COPY --from=vendor /var/www/html /var/www/html

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD wget -qO- http://127.0.0.1:8000/up >/dev/null || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
