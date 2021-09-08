#!/bin/bash

cd /app/api/v1
cp config/autoload/local.php.dist config/autoload/local.php
composer install
mkdir -p logs
mkdir -p data
chmod -R 777 logs
chmod -R 777 data
./migrations migrate
#set -e
#service apache2 start
exec "$@"

	



