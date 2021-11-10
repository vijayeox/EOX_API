Some quick tips on how to use deploy app:


Scenario 1: Deploy App - path only
=========================================

1. If the docker 'path' to the application is specified, the complete setup of app takes place.



Scenario 2: Deploy App - path + parameters
===========================================

1. Choose any of the 'parameters' options: initialize, entity, workflow, form, page,
	menu, job

2. If you choose to deploy page then 'parameters' to be specified is: page, menu

3. If you choose to deploy menu then 'parameters' to be specified is: menu

4. All the options must be written in CSV format.


Run composer to setup the test environment
===========================================
$ composer install

Set the .env file with the location of the API Folder. Refer the .env.sample for reference
Run the phpunit from the application HOME folder
=================================================
$ ./phpunit