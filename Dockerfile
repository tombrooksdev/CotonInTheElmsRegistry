FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod remoteip headers \
    && sed -ri 's!AllowOverride None!AllowOverride All!' /etc/apache2/apache2.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php=Off\n' > "$PHP_INI_DIR/conf.d/hardening.ini"

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY index.html submit.php confirm.php admin.php bootstrap.php .htaccess logo.svg favicon.svg favicon-32.png apple-touch-icon.png ./
COPY config.env.php ./config.php
RUN chown -R www-data:www-data /var/www/html

HEALTHCHECK --interval=30s --timeout=3s CMD php -r 'exit(@file_get_contents("http://localhost/index.html") ? 0 : 1);'
