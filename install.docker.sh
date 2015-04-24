#!/usr/bin/env bash

cd /var/www/minifier/
php -d memory_limit=-1 /usr/local/bin/composer install