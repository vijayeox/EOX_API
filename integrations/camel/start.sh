#!/bin/bash

if [ ! -e ./.env ]; then
	echo "Please set .env file up"
	exit
fi

dirName="$(tr [A-Z] [a-z] <<< "${PWD##*/}")"
echo "Stopping container if already running..."
docker stop "${dirName//_}_camel_1"

BACKGROUND=false
IP=`hostname -I | awk '{ print $1 }'`

while getopts "h:YyNn" options
do
	case $options in
			h ) IP=$OPTARG;;
		[Yy]* ) startBash=y BACKGROUND=true;;
		[Nn]* ) startBash=n BACKGROUND=true;;
	esac
done

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${IP}/" .env

chmod 777 -R ./docker-entrypoint.sh

if [ ${BACKGROUND} == true ]; then
	IP="$IP" docker-compose up -d --build
else
	IP="$IP" docker-compose up --build
fi

echo "Camel and ActiveMQ are being served in the background on port 8085 and 8161 respectively."
echo "Please wait for few seconds before the service is available on the browser."

while true; do
	case $startBash in
		[Yy]* ) docker exec -it "${dirName//_}_camel_1" bash; break;;
		[Nn]* ) break;;
			* ) read -p "Do you wish to enter the container?(y/n)" startBash;;
	esac
done
