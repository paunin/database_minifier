FROM php:5.6
ENV EDITOR vim

ADD php-custom.ini /usr/local/etc/php/conf.d/php-custom.ini

RUN apt-get update && \
    apt-get install -y git php5-dev php-pear php5-xdebug php5-cli php5-common php5-mysql mysql-client && \
    docker-php-ext-install mysql pdo_mysql zip && \
    echo "export PHP_IDE_CONFIG=\"serverName=minifier\"" >> ~/.bashrc && \
    echo 'export TERM=xterm' >> ~/.bashrc && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD /var/www/minifier/install.docker.sh

