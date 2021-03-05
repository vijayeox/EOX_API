#!/bin/bash

if [ ! -e ./.env ]; then
	echo "Please set .env file up"
	exit
fi

IP=`hostname -I | awk '{ print $1 }'`
sed -ri -e "s/^HOST=.*/HOST=$IP/" \
	-ri -e "s/^DB_HOST=.*/DB_HOST=$IP/" \
	.env

docker-compose up -d --build

getopts ":yn" yn
while true; do
    case $yn in
        [Yy]* ) docker exec -it "${PWD##*/}_zf_1" bash; break;;
        [Nn]* ) break;;
        * ) read -p "Do you wish to enter the container?(y/n)" yn;;
    esac
done