<div align="center">
  <h1>EOS-3.0 DEV ENVIRONMENT SETUP</h1>
  <p>
    Development Environment setup for application development at EOX Vantage.
  </p>
</div>
----

<div align="center">
<h3><u>Installations</u>:</h3>
</div>
-------------------------
- Please install the recommened markdown reader by the command mentioned below to read this readme properly.

$ sudo apt install remarkable
	Note: For ubuntu 20+ use snap install remarkable

<h4> 1. <u>Install Smartgit</u>: </h4>

<h4>Install git command line</h4>

- $ sudo apt install git

<h4>Download Smartgit from the Official Website</h4>

- Download the debian bundle of smartgit for easy installation

![smartgit-installation](deployment/static/gif/installsmartgit.gif)
<h5 align="center">GIF: HOW TO INSTALL SMARTGIT</center></h5>
    
- Once Installed you can generate ssh keys and update them in your gitlab account for you to connect the git repository and enable you to do version control without password or you can enter your git account credential to pull code.

![key generation](deployment/static/gif/ssh-keygen.gif)
<h5 align="center">GIF: HOW TO GENERATE KEYS</h5>
- After you have generated the keys update the public part of key to gitlab account 

![](deployment/static/gif/addingpubkeygitlab.gif)
<h5 align="center">GIF: HOW TO ADD KEYS TO GITLAB</center></h5>

- Open Smartgit and goto repository tab and select clone to start cloning the codebase to your local machine.
- You need to know the gitlab repository address to clone it which you can find in the gitlab server i.e `code.oxzion.com`.
- We have different branches for different projects going on. The QA branch is for Development Team. Please clone or checkout if already cloned to the required branch to work on.

![clone](deployment/static/gif/smartgitclone.gif)
<h5 align="center">GIF: HOW TO CLONE A REPOSITORY IN SMARTGIT</center></h5>

<h4> 2. <u>Docker</u>: </h4>

To learn how to install Docker [click here.](https://www.digitalocean.com/community/tutorials/how-to-install-and-use-docker-on-ubuntu-18-04)

<h5><center> OR RUN THE FOLLOWING COMMANDS in terminal</center></h5>

- sudo apt update
- sudo apt install apt-transport-https ca-certificates curl software-properties-common

- curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -

- sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu bionic stable"

- sudo apt update
- sudo apt install docker-ce
	Note: If you are using ubuntu 20+ then follow the steps from <a href='https://docs.docker.com/engine/install/ubuntu/'>here</a>:

<h5>Docker should now be installed, the daemon started, and the process enabled to start on boot. Check that it’s running:</h5>

- sudo systemctl status docker

			Note: Use `sudo` to run docker command
		
-----------

### 4. <u>Run application using [docker-compose](https://docs.docker.com/compose/)</u>

- For global credential update, copy .env.example to .env and change the credential according to your ip and other credentislas  
```bash
 $ cp  .env.example  .env
``` 
- To build the Docker image use the following command 
```bash
$ sudo docker-compose build 
```

- To start api, camunda workflow, camel with activemq, view run below command 

```bash
$ docker-compose up --force-recreate
```
- To connect to the container using bash run the following command
```bash
$ docker exec -it setup_eox_1 bash
```

### 5. <u>You can access the application through the [url](http://localhost:8081)</u>
### 6. <u>The server API is available through the [url](http://localhost:8080)</u>
### 7. <u>You can access the mysql using phpmyadmin through the [url](http://localhost:8080/phpmyadmin)</u>
### 8. <u>You can access the ActiveMq console through the [url](http://localhost:8161/)</u>
### 9. <u>You can connect to mysql from the bash prompt using mysql -hlocalhost -u<user> -p</u>
### 10. <u>To connect to mysql from tools on your host machine run tne following sql, restart the docker and connect using port 3307.</u>
```bash
UPDATE mysql.user set host = '%' where user = 'root';
```

### Note 1: if you start application using docker-compose at root level of the project, then you have to update crdential only in .env file at root level, but if you run each application separately by entering into services, then update .env at respective places.
