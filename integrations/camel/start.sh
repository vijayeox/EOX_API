#!/bin/bash

execOptions() {
	containerName=`docker container ls -la -f name="$dirName" --format "{{.Names}}"`
	containerStatus=`docker container inspect --format="{{.State.Status}}" $containerName`
	if [ "$containerStatus" != "exited" ]; then
		while true; do
			case $startOptions in
				[Ii]* ) echo "==================================================";
						echo "			Logs";
						echo "==================================================";
						docker logs -n100 -f "$containerName";
						break;;
				[Yy]* ) docker exec -it "$containerName" bash; break;;
				[Nn]* ) break;;
					* ) read -p "Do you wish to enter the container or display logs?(y/n/i)" startOptions;;
			esac
		done
		exit
	fi
}

if [ ! -f ./.env ]; then
	echo "Please set .env file up"
	exit
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

dirName="$(tr [A-Z] [a-z] <<< "${PWD##*/}")";
containerName=`docker container ls -la -f name="$dirName" --format "{{.Names}}"`
if [ "$containerName" ]; then
	containerStatus=`docker container inspect --format="{{.State.Status}}" $containerName`
	if [ "$containerStatus" != "exited" ] && [ "$startOptions" == "i" ] || [ "$startOptions" == "y" ]; then
		currentIp=`docker exec -it $containerName printenv -0 HOST`
		echo ""
		if [ "$currentIp" == "$IP" ]; then
			read -p "Do you want to restart the service?(y/n)" restart
			if [ "$restart" == "n" ]; then
				execOptions startOptions containerName
			fi
		fi
	fi
	echo "Stopping container if already running..."
	docker stop "$containerName"
fi

sed -ri -e "s/([0-9]{1,3}\.){3}[0-9]{1,3}/${IP}/" .env

chmod 777 -R ./docker/entrypoint.sh

docker-compose up -d --build
echo "Camel and ActiveMQ are being served in the background on port 8085 and 8161 respectively."
echo "Please wait for few seconds before the service is available on the browser."
execOptions startOptions containerName