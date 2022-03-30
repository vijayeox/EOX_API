#!/bin/bash

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/127.0.0.1/" .env

rm -rf ./IdentityService/.gradle
rm -rf ./IdentityService/build
rm -rf ./IdentityService/dist

rm -rf ./ProcessEngine/.gradle
rm -rf ./ProcessEngine/build
rm -rf ./ProcessEngine/dist
rm ./ProcessEngine/src/main/resources/application.properties
cp ./ProcessEngine/src/main/resources/application.properties.example ./ProcessEngine/src/main/resources/application.properties

docker rm wf_build
docker rm workflow-wf-1