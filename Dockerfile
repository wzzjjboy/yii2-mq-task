FROM php:7.4.29-cli-alpine3.15

ARG swoole_ver
ENV SWOOLE_VER=${swoole_ver:-"v4.4.15"}

RUN set -ex \
&& cd /tmp \
&& sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
&& apk update \
&& apk add vim git autoconf openssl-dev build-base zlib-dev re2c libpng-dev oniguruma-dev

# install composer
RUN cd /tmp \
&& wget https://mirrors.aliyun.com/composer/composer.phar \
&& chmod u+x composer.phar \
&& mv composer.phar /usr/local/bin/composer \
&& composer config -g repo.packagist composer https://mirrors.aliyun.com/composer \
&& echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc

# php ext
#RUN php -m \
RUN docker-php-ext-install gd pdo_mysql mysqli sockets pcntl \
&& php -m


# install swoole
RUN cd /tmp \
&& git clone https://gitee.com/swoole/swoole swoole \
&& cd swoole \
&& git checkout ${SWOOLE_VER} \
&& phpize \
&& ./configure --enable-openssl --enable-sockets --enable-http2 --enable-mysqlnd \
&& make \
&& make install \
&& docker-php-ext-enable swoole \
&& php -m \
&& php --ri swoole

#install sdebug
#RUN git clone https://gitee.com/wzzjjboy/sdebug.git sdebug \
#&& cd sdebug \
#&& git checkout sdebug_2_7 \
#&& phpize \
#&& ./configure --enable-xdebug \
#&& make \
#&& make install \
#&& docker-php-ext-enable xdebug \
#&& php --ri sdebug \
#&& php -m \
#&& php --ri sdebug \


# config php
#&& cd /usr/local/etc/php/conf.d \
#&& echo "swoole.use_shortname = off" >> 99-off-swoole-shortname.ini \
#&& { \
#echo "[Xdebug]"; \
#echo "xdebug.remote_enable = 1"; \
#echo ";xdebug.remote_connect_back = On"; \
#echo "xdebug.remote_autostart = true"; \
#echo "xdebug.remote_host = host.docker.internal"; \
#echo "xdebug.remote_port = 19000"; \
#echo "xdebug.idekey=PHPSTORM"; \
#} | tee 99-xdebug-enable.ini \


# install phpredis
RUN cd /tmp \
&& git clone https://gitee.com/mirrors/phpredis phpredis \
&& cd phpredis \
&& phpize \
&& ./configure \
&& make \
&& make install \
&& docker-php-ext-enable redis \
&& php -m \
&& php --ri redis

#install mongodb
RUN pecl install mongodb \
&& docker-php-ext-enable mongodb

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
&& php --ri phptars

#install amqp
RUN apk add --no-cache -U autoconf \
&& apk add --no-cache -U gcc \
&& apk add --no-cache -U libc-dev \
&& apk add --no-cache -U make \
&& apk add --no-cache rabbitmq-c \
&& apk add --no-cache rabbitmq-c-dev \
&& pecl install amqp \
&& docker-php-ext-enable amqp \

&& apk add yaml yaml-dev \
&& pecl install yaml \
&& docker-php-ext-enable yaml \

&& rm -rf /tmp/* \
&& php -v \
&& php -m \
&& echo -e "Build Completed!"

EXPOSE 9501
WORKDIR /app
CMD ["/bin/ash", "/usr/local/bin/start_task.sh"]