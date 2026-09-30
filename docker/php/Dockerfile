# Cùng nhánh PHP 8.3 với môi trường phát triển (Laragon trên Windows).
# Ubuntu 22.04 mặc định là PHP 8.1, còn Laravel 13 cần ít nhất 8.3, nên chạy
# trong image PHP 8.3 giữ đúng runtime giữa dev và production.
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libxml2-dev libpng-dev libonig-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl bcmath gd opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Nạp và xuất tệp lớn cần bộ nhớ và thời gian hơn mặc định; con số cuối cùng
# phải chốt theo bài đo tải trên chính máy chủ của bệnh viện.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/mediguard.ini

WORKDIR /var/www/html

# Cài thư viện trước khi chép mã nguồn để tận dụng cache tầng image.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
