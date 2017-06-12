FROM php:5.6-apache

RUN docker-php-ext-install -j$(nproc) pdo

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/*
