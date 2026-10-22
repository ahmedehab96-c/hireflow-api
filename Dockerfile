FROM php:8.4.27-cli-bookworm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libsqlite3-0 \
    libsqlite3-dev \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite intl zip bcmath gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
# Keeps dev deps (fakerphp/faker) — the demo seeder uses fake() for demo data.
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . ./

RUN composer dump-autoload --optimize \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache database

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
