#!/bin/bash

DB_NAME=${1}
SQL_FILE=${2}

if [[ ! -e "$PWD/sql/$SQL_FILE" ]];
then
	echo "The ${SQL_FILE} file doesn't exist! Please Check if the file exists in the sql directory!"
else
	echo "Starting sql import"
	docker-compose exec -e DB_NAME=${DB_NAME} -e SQL_FILE=${SQL_FILE} mysql-5.6 bash /scripts/dbimport.sh
fi

