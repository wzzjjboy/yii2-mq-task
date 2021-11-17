FROM php:7.3.17-cli-alpine3.11

RUN set -ex \
&& cd /tmp \
&& sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
&& apk update \
&& apk add vim git autoconf openssl-dev build-base zlib-dev re2c libpng-dev oniguruma-dev

# install composer
RUN cd /tmp \&& wget https://mirrors.aliyun.com/composer/composer.phar \
&& chmod u+x composer.phar \
&& mv composer.phar /usr/local/bin/composer \
&& composer config -g repo.packagist composer https://mirrors.aliyun.com/composer \
&& echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc

# install ext
RUN apk add libzip-dev \
&& docker-php-ext-install bcmath pcntl zip pdo_mysql

# install swoole
RUN pecl install swoole-4.5.11 && docker-php-ext-enable swoole

# install amqp
RUN apk add rabbitmq-c-dev \
&& pecl install amqp-1.10.0 && docker-php-ext-enable amqp

# install gd
RUN apk add libpng libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev gd \
&& docker-php-ext-install gd

# install mongodb
RUN pecl install mongodb && docker-php-ext-enable mongodb

#install phptars
RUN cd /tmp \
&& git clone https://github.com/TarsPHP/tars-extension.git \
&& cd tars-extension \
&& phpize \
&& ./configure \
&& make \
&& make install \
&& docker-php-ext-enable phptars \
&& php -m \
&& php --ri phptars \
&& rm -rf /tmp/tars-extension

WORKDIR /app

CMD ["php yii mq/start"]