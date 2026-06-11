FROM php:8.2-cli

ARG USER_ID=1000
ARG GROUP_ID=1000

# Устанавливаем системные зависимости и расширения через apt
RUN apt-get update && apt-get install -y \
    libpq-dev \
    librabbitmq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo_pgsql bcmath zip sockets

RUN pecl install amqp && docker-php-ext-enable amqp

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создаём группу и пользователя с теми же UID/GID, что и на хосте
RUN groupadd -g ${GROUP_ID} appuser || true && \
    useradd -u ${USER_ID} -g ${GROUP_ID} -m -s /bin/bash appuser || true

WORKDIR /var/www/html

# Переключаемся на непривилегированного пользователя
USER appuser

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
