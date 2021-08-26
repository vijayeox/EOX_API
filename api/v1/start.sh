#!/bin/bash

if [ ! -e ./.env ]; then
	echo "Please set .env file up"
	exit
fi

echo "Stopping container if already running..."
dirName="$(tr [A-Z] [a-z] <<< "${PWD##*/}")";
docker stop "${dirName//_}_zf_1"

IP=`hostname -I | awk '{ print $1 }'`

while getopts "h:YyNn" options
do
	case $options in
			h ) IP=$OPTARG;;
		[Yy]* ) startBash=y;;
		[Nn]* ) startBash=n;;
	esac
done

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${IP}/" .env

docker-compose up -d --build
echo "API is being served in the background on port 8080."

while true; do
    case $startBash in
		[Yy]* ) docker exec -it "${dirName//_}_zf_1" bash; break;;
        [Nn]* ) break;;
			* ) read -p "Do you wish to enter the container?(y/n)" startBash;;
    esac
done