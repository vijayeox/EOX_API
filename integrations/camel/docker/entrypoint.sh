#!/bin/bash

cd /workspace/app

if [[ ${SMTP_HOST} == "localhost" ]]; then
    echo "Starting SMTP Server..."
    cd ./../../app/integrations/smtp/smtp-server
    chmod 777 ./startup.sh
    ./startup.sh
    cd /workspace/app
fi

echo "Starting camel..."
echo "${HOST}"
cp ./src/main/resources/application.yml.example ./src/main/resources/application.yml
sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${DB_HOST}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{DB_USERNAME\}\})/${DB_USERNAME}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{DB_PASSWORD\}\})/${DB_PASSWORD}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{API_DB\}\})/${API_DB}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{QUARTZ_DB\}\})/${QUARTZ_DB}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{SMTP_HOST\}\})/${SMTP_HOST}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{SMTP_PORT\}\})/${SMTP_PORT}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{SMTP_USERNAME\}\})/${SMTP_USERNAME}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{SMTP_PASSWORD\}\})/${SMTP_PASSWORD}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{SMTP_EMAIL_FROM\}\})/${SMTP_EMAIL_FROM}/" ./src/main/resources/application.yml
sed -ri -e "s/(\{\{SMTP_EMAIL_TO\}\})/${SMTP_EMAIL_TO}/" ./src/main/resources/application.yml

cp ./src/main/resources/mail.properties.example ./src/main/resources/mail.properties
sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${HOST}/" ./src/main/resources/mail.properties
sed -ri -e "s/(\{\{SMTP_HOST\}\})/${SMTP_HOST}/" ./src/main/resources/mail.properties
sed -ri -e "s/(\{\{SMTP_PORT\}\})/${SMTP_PORT}/" ./src/main/resources/mail.properties
sed -ri -e "s/(\{\{SMTP_USERNAME\}\})/${SMTP_USERNAME}/" ./src/main/resources/mail.properties
sed -ri -e "s/(\{\{SMTP_PASSWORD\}\})/${SMTP_PASSWORD}/" ./src/main/resources/mail.properties
sed -ri -e "s/(\{\{SMTP_EMAIL_FROM\}\})/${SMTP_EMAIL_FROM}/" ./src/main/resources/mail.properties
sed -ri -e "s/(\{\{SMTP_EMAIL_TO\}\})/${SMTP_EMAIL_TO}/" ./src/main/resources/mail.properties

cp ./src/main/resources/oxzion.properties.example ./src/main/resources/oxzion.properties
sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${DB_HOST}/" ./src/main/resources/oxzion.properties
sed -ri -e "s/(\{\{DB_USERNAME\}\})/${DB_USERNAME}/" ./src/main/resources/oxzion.properties
sed -ri -e "s/(\{\{DB_PASSWORD\}\})/${DB_PASSWORD}/" ./src/main/resources/oxzion.properties
sed -ri -e "s/(\{\{API_DB\}\})/${API_DB}/" ./src/main/resources/oxzion.properties
sed -ri -e "s/(\{\{ELASTIC_CLUSTER\}\})/${ELASTIC_CLUSTER}/" ./src/main/resources/oxzion.properties

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${HOST}/" ./src/main/resources/Routes.groovy

#check if .gradle is there if not create
if [[ ! -d "./.gradle" ]]; then
    mkdir .gradle
    chmod 777 .gradle
fi

#create a link from home .gradle folder to our workspace .gradle folder
if [[ -d "/root/.gradle" && ! -L "/root/.gradle" ]]; then
    echo "removing /root/.gradle"
    rm -Rf /root/.gradle
fi
if [[ ! -L "/root/.gradle" ]]
then
    ln -s /workspace/app/.gradle /root/.gradle
fi

chmod 777 ./gradlew
./gradlew bootJar
mkdir -p /workspace/camel
cp ./build/libs/app-0.0.1-SNAPSHOT.jar /workspace/camel/camel.jar
su - activemq /opt/activemq/bin/activemq console &
cd /workspace/camel
java -jar ./camel.jar
