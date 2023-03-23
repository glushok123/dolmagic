FROM php:8.1-cli-alpine

RUN apk update && apk add git

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    @composer-1.10.22 \
    opcache mcrypt \
    intl gmp imap \
    pdo_mysql pdo_dblib \
    tidy redis xmlrpc \
    zip imagick gd exif

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_MEMORY_LIMIT -1

COPY ./conf.d /usr/local/etc/php/conf.d

WORKDIR /app