FROM php:fpm-alpine

RUN apk add --no-cache oniguruma-dev libzip-dev zip unzip \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo_sqlite zip

COPY --from=composer:2-alpine /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN composer install --no-interaction --optimize-autoloader --no-dev

EXPOSE 9000

CMD ["php-fpm"]