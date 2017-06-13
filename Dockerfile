FROM php:5.6-apache

RUN docker-php-ext-install -j$(nproc) bcmath pdo

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
RUN chown www-data:www-data /var/www
