FROM php:8.3-apache

# update
RUN apt update

# SW needed
RUN apt install make -y --fix-missing
RUN apt install ccache apt-utils -y --fix-missing
RUN apt install gcc -y --fix-missing

# GD
RUN apt install zlib1g-dev libpng-dev libavif-dev libwebp-dev libjpeg-dev libxpm-dev libfreetype6-dev -y --fix-missing
RUN docker-php-ext-configure gd --enable-gd --with-jpeg --with-freetype --with-webp --with-avif --with-xpm
RUN docker-php-ext-install -j "$(nproc)" gd

# PDO
RUN apt install libpq-dev -y --fix-missing
RUN docker-php-ext-install -j "$(nproc)" pdo
RUN docker-php-ext-install -j "$(nproc)" pdo_mysql
RUN docker-php-ext-install -j "$(nproc)" pdo_pgsql

# Opcache
RUN docker-php-ext-configure opcache --enable-opcache
RUN docker-php-ext-install -j "$(nproc)" opcache

# google
RUN docker-php-ext-install -j "$(nproc)" bcmath

# crypting messages
RUN apt install libssl-dev -y --fix-missing
RUN docker-php-ext-install -j "$(nproc)" openssl; exit 0
RUN cp /usr/src/php/ext/openssl/config0.m4 /usr/src/php/ext/openssl/config.m4
RUN docker-php-ext-install -j "$(nproc)" openssl

# search by ascii
RUN apt install libicu-dev -y --fix-missing
RUN docker-php-ext-install -j "$(nproc)" intl

# Custom Opcache
RUN ( \
  echo "opcache.enable=1"; \
  echo "opcache.jit_buffer_size=256M"; \
  echo "opcache.memory_consumption=128"; \
  echo "opcache.interned_strings_buffer=8"; \
  echo "opcache.max_accelerated_files=20000"; \
  echo "opcache.revalidate_freq=5"; \
  echo "opcache.fast_shutdown=1"; \
  echo "opcache.enable_cli=1"; \
  ) >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# mod rewrite
RUN a2enmod rewrite
RUN service apache2 restart

# set ServerName and prevent some warning
RUN echo "ServerName web-project-web" >> /etc/apache2/apache2.conf

# Installation of Composer
RUN apt install git zip libzip-dev -y --fix-missing
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Custom php.ini
COPY ./.docker/web/config/php.ini /usr/local/etc/php/

# install npm and node.js
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /var/www/html