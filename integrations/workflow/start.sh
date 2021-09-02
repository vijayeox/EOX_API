#!/bin/bash

if [ ! -f ./.env ]; then
	echo "Please set .env file up"
	exit
fi

dirName="$(tr [A-Z] [a-z] <<< "${PWD##*/}")"
containerName=$dirName'_wf_1'
containerStatus="$(docker container inspect --format="{{.State.Status}}" $containerName)"

if [ $containerStatus != "exited" ]; then
	echo "Stopping container if already running..."
	docker stop $containerName
fi

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

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/$IP/" .env
export $(cat .env | sed 's/#.*//g' | xargs)
export $(cat ./ProcessEngine/src/main/resources/application.properties | sed 's/#.*//g' | xargs)

if [ "http://$IP:8080" != "$applicationurl" ]; then

	cp ./ProcessEngine/src/main/resources/application.properties.example ./ProcessEngine/src/main/resources/application.properties

	sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/$IP/" ./ProcessEngine/src/main/resources/application.properties
	sed -ri -e "s/(\{\{DB_USERNAME\}\})/$DB_USERNAME/" ./ProcessEngine/src/main/resources/application.properties
	sed -ri -e "s/(\{\{DB_PASSWORD\}\})/$DB_PASSWORD/" ./ProcessEngine/src/main/resources/application.properties
	sed -ri -e "s/(\{\{API_DB\}\})/$API_DB/" ./ProcessEngine/src/main/resources/application.properties

	chmod 777 ./docker/entrypoint.sh

	docker rm wf_build
	docker build -t workflow_build docker
	docker run --network="host" --name="wf_build" -it -v ${PWD}:/camunda workflow_build

fi

if [ $startOptions == "i" ]; then
	IP="$IP" docker-compose up --build
else
	IP="$IP" docker-compose up -d --build
	echo "Camunda is being served in the background on port 8090/camunda";
	while true; do
		case $startOptions in
			[Yy]* ) docker exec -it "${dirName//_}_wf_1" bash; break;;
			[Nn]* ) break;;
				* ) read -p "Do you wish to enter the container?(y/n)" startOptions;;
		esac
	done

fi
