FROM php:alpine

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-configure pcntl --enable-pcntl \
 && docker-php-ext-install pcntl

COPY . /opt/netfind

WORKDIR /opt/netfind

RUN composer install

RUN apk add net-tools nmap networkmanager

ENTRYPOINT ["/opt/netfind/builds/netfind"]
