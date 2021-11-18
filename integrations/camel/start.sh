#!/bin/bash

if [ ! -f ./.env ]; then
	echo "Please set .env file up"
	exit
fi

dirName="$(tr [A-Z] [a-z] <<< "${PWD##*/}")"
echo "Stopping container if already running..."
docker stop "${dirName//_}-ca-1"

IP=`hostname -I | awk '{ print $1 }'`

startOptions=""
while getopts "h:YyNnIi" options
do
	case $options in
			h ) IP=$OPTARG;;
		[Yy]* ) startOptions="y";;
		[Nn]* ) startOptions="n";;
		[Ii]* ) startOptions="i";;
	esac
done

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${IP}/" .env

chmod 777 -R ./docker/entrypoint.sh

if [ $startOptions == "i" ]; then
	IP="$IP" docker-compose up --build
else
	IP="$IP" docker-compose up -d --build
	echo "Camel and ActiveMQ are being served in the background on port 8085 and 8161 respectively."
	echo "Please wait for few seconds before the service is available on the browser."
	while true; do
		case $startOptions in
			[Yy]* ) docker exec -it "${dirName//_}-ca-1" bash; break;;
			[Nn]* ) break;;
				* ) read -p "Do you wish to enter the container?(y/n)" startOptions;;
		esac
	done

fi