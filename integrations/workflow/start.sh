#!/bin/bash

if [ ! -e ./.env ]; then
	echo "Please set .env file up"
	exit
fi

dirName="$(tr [A-Z] [a-z] <<< "${PWD##*/}")"
echo "Stopping container if already running..."
docker stop "${dirName//_}_wf_1"

IP=`hostname -I | awk '{ print $1 }'`

while getopts "h:YyNn" options
do
	case $options in
			h ) IP=$OPTARG;;
		[Yy]* ) startBash=y;;
		[Nn]* ) startBash=n;;
	esac
done

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/$IP/" .env
if [ -f .env ]; then
  export $(cat .env | sed 's/#.*//g' | xargs)
fi
export $(cat ./ProcessEngine/src/main/resources/application.properties | sed 's/#.*//g' | xargs)

if [ "http://$IP:8080" != "$applicationurl" ]; then

	cp ./ProcessEngine/src/main/resources/application.properties.example ./ProcessEngine/src/main/resources/application.properties

	sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/$IP/" ./ProcessEngine/src/main/resources/application.properties
	sed -ri -e "s/(\{\{DB_USERNAME\}\})/$DB_USERNAME/" ./ProcessEngine/src/main/resources/application.properties
	sed -ri -e "s/(\{\{DB_PASSWORD\}\})/$DB_PASSWORD/" ./ProcessEngine/src/main/resources/application.properties
	sed -ri -e "s/(\{\{API_DB\}\})/$API_DB/" ./ProcessEngine/src/main/resources/application.properties

	chmod 777 ./docker/entrypoint.sh

	docker rm wf_build
	docker build -t workflow_build docker && docker run --network="host" --name="wf_build" -it -v ${PWD}:/camunda workflow_build

fi

IP="$IP" docker-compose up --build
# IP="$IP" docker-compose up -d --build

# echo "Camunda is being served in the background on port 8090/camunda"

# while true; do
# 	case $startBash in
# 		[Yy]* ) docker exec -it "${dirName//_}_wf_1" bash; break;;
# 		[Nn]* ) break;;
# 			* ) read -p "Do you wish to enter the container?(y/n)" startBash;;
# 	esac
# done
