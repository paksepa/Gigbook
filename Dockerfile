# Optional: Betrieb per Docker (lokal oder auf einem VPS).
# Auf dem netcup-Webspace wird KEIN Docker benötigt – siehe README.
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev \
 && docker-php-ext-install zip \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!/var/www/app/!g; s!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && echo 'ServerTokens Prod' > /etc/apache2/conf-enabled/zz-gigbook.conf \
 && echo 'ServerSignature Off' >> /etc/apache2/conf-enabled/zz-gigbook.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --chown=www-data:www-data . /var/www/app
RUN mkdir -p /var/www/app/data && chown -R www-data:www-data /var/www/app/data

WORKDIR /var/www/app
VOLUME /var/www/app/data
EXPOSE 80
