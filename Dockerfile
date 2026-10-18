# 1) Собранный фронтенд Хекслета — забираем из npm-пакета
FROM node:26-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --omit=dev

# 2) Зависимости PHP без dev-пакетов
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist

# 3) Рантайм
FROM php:8.4-cli-alpine

# Драйвер pdo_pgsql собирается из исходников: нужны заголовки клиента PostgreSQL (libpq-dev).
# Инструменты сборки удаляем сразу, в образе остаётся только libpq.
RUN apk add --no-cache bash make libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apk del .build-deps

# Производственный php.ini: без него PHP показывает ошибки прямо в ответе, то есть посетитель
# увидит стектрейс с путями и запросами. В проде ошибки идут в лог, а не в браузер.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app
COPY . .
COPY --from=vendor /build/vendor ./vendor
COPY --from=frontend /build/node_modules/@hexlet/php-flight-booking-frontend/dist/. ./public/

CMD ["make", "start"]
