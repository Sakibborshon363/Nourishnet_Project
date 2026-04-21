FROM php:8.2-apache

# PDO MySQL extension install
RUN docker-php-ext-install pdo pdo_mysql

# Apache mod_rewrite enable
RUN a2enmod rewrite

# Working directory
WORKDIR /var/www/html
