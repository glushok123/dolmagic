FROM php:8.1-fpm-alpine

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    opcache mcrypt \
    intl gmp imap \
    pdo_mysql \
    tidy redis xmlrpc \
    zip imagick gd exif

COPY ./conf.d /usr/local/etc/php/conf.d

WORKDIR /app