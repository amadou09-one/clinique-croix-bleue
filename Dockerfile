FROM php:8.3-apache

# Dépendances système + extensions PHP nécessaires à Laravel + PostgreSQL
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Apache doit servir public/ (front controller Laravel) avec AllowOverride All,
# sinon le .htaccess de Laravel (rewrite vers index.php) est ignoré et toutes
# les routes renvoient un 404 Apache brut au lieu de passer par Laravel.
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
