#Copy the .env.example to .env file and update the credentials for mysql that you want.

cp env.example .env

#To run services in foreground mode

$ docker-compose up

#To run services in background mode

$ docker-compose up -d

#To shut down the services in background mode run this from the directory where the docker-compose.yml exists

$ docker-compose down

#If running in Foreground mode

CTRL+C on the running terminal will start the shutdown of the services.

Note: 

1) After the services have started successfully PhPMyAdmin page is available at localhost:8082. Also to connect mysql from a client application running on the host machine use 3307 as the port the configurations.

2) A directory named 'sql' has been provided and it can be used to keep the scripts and database dumps.

3) To import any sql script including database dump a shell script has been provided for easy use. It uses two parameters to import. First the "database_name" and second the "dot-sql_filename" (.sql).


#To import database using script

$ bash import.sh database_name dot-sql_filename


