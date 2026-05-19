FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install zip \
    && docker-php-ext-install sockets

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создаем пользователя с UID 1000 (совпадает с хостовой системой)
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

WORKDIR /var/www

COPY --chown=www:www . .

RUN composer install --no-interaction --optimize-autoloader

# Убеждаемся, что все необходимые каталоги имеют правильного владельца
RUN chown -R www:www /var/www/storage /var/www/bootstrap/cache /var/www/bootstrap/cache/*
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
