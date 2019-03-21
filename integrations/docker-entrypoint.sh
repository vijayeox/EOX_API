#!/bin/sh

cd rainloop 
npm install 
npm audit fix
gulp rainloop:start

cp build/dist/releases/webmail/1.12.1/src/*.php /var/www/public/rainloop
mkdir /var/www/public/rainloop/rainloop
cp -R build/dist/releases/webmail/1.12.1/src/rainloop /var/www/public/rainloop/
cp build/dist/releases/webmail/1.12.1/src/data/* /var/www/public/rainloop/data

cp -R /integrations/eventcalendar/* /var/www/public/calendar   
chown www-data:www-data -R /var/www/public

cp -ra /integrations/orocrm/. /var/www/public/crm/
cd /var/www/public/crm/
chmod 777 -R /var/www/public/crm/
composer install
php ./bin/console oro:install --env=prod --timeout=30000 --application-url="http://localhost:8075/crm/public" --organization-name="Vantage Agora" --user-name="admin" --user-email="admin@example.com" --user-firstname="Admin" --user-lastname="User" --user-password="admin" --language=en --formatting-code=en_US
cp ./orocrm_supervisor.conf /etc/supervisor/conf.d/

service apache2 start
service supervisor restart
tail -f /dev/null &