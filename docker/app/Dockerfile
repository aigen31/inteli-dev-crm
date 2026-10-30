FROM php:8.5-fpm-bookworm AS base

# Use faster Debian mirror
RUN sed -i 's|deb.debian.org|mirror.yandex.ru|g' /etc/apt/sources.list.d/debian.sources

# Install PHP extensions via install-php-extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    intl \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    bcmath \
    opcache \
    pcntl \
    redis

# PHP configuration
COPY docker/app/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/app/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www

# ─── Composer stage ───────────────────────────────────────────────
FROM base AS composer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# ─── Node build stage ────────────────────────────────────────────
FROM node:24-alpine AS node

WORKDIR /var/www
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=composer /var/www/vendor/tightenco/ziggy vendor/tightenco/ziggy
RUN npm run build

# ─── Production image ────────────────────────────────────────────
FROM base AS production

# Create app user
RUN groupadd -g 1000 app && useradd -u 1000 -g app -m app

COPY --from=composer --chown=app:app /var/www /var/www
COPY --from=node --chown=app:app /var/www/public/build /var/www/public/build

# Keep a pristine copy of public/ so the entrypoint can sync it
# into the shared named volume on every container start.
RUN cp -a /var/www/public /var/www/public-build

RUN mkdir -p /var/www/storage/logs \
             /var/www/storage/framework/sessions \
             /var/www/storage/framework/views \
             /var/www/storage/framework/cache \
             /var/www/storage/app/attachments \
    && chown -R app:app /var/www/storage /var/www/bootstrap/cache

COPY --chmod=755 docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh

USER app

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
