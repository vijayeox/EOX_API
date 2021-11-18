#!/bin/bash

if [ ! -f ./.env ]; then
	echo "Please set .env file up"
	exit
fi

echo "Stopping container if already running..."
dirName="$(tr [A-Z] [a-z] <<< "${PWD##*/}")";
docker stop "${dirName//_}-zf-1"

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
# sed -ri -e "s/^HOST=.*/HOST=$IP/" \
# 	-ri -e "s/^DB_HOST=.*/DB_HOST=$IP/" \
# 	.env

if [ "$startOptions" == "i" ]; then
	docker-compose up --build
else
	docker-compose up -d --build
	echo "API is being served in the background on port 8080."
	while true; do
		case $startOptions in
			[Yy]* ) docker exec -it "${dirName//_}-zf-1" bash; break;;
			[Nn]* ) break;;
				* ) read -p "Do you wish to enter the container?(y/n)" startOptions;;
		esac
	done
fi
