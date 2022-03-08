<div align="center">
  <h1>EOS-3.0 BUILD AND DEPLOY</h1>
  <p>
    To package and deploy oxzion-3.0 to production.
  </p>
</div>
----

<div align="center">
<h3><u>Usage</u>:</h3>
</div>
-------------------------


- Use the script **_build.sh_** to build and package and **_deploy.sh_** to deploy.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;_[Note: The deploy script is for production server]_
- Use the script **_buildimages.sh_** to build all the docker images in oxzion3.0.
- Use the script **_freshsetup.sh_** to do a fresh setup.

-------------------------

<div align="center">
<h3><u>To Run the Script</u>:</h3>
</div>

--------------------------

**_This script take 3 parameters to run._**

1. Build Name like **api** **calendar**
2. Server name
3. Identity File Path(PEM/PPK)

	>For example

	<h5>bash **_build.sh calendar abc@xyz.com ~/.ssh/abc.pem_**</h5>

-------------------------

<div align="center">
<h3><u>Server Side Software Setup</u>:</h3>
</div>
-------------------------
<h4>Step 1: <u>Install Software Prerequisites:-</u></h4>

-------------------------
<h4>1. <u>Node.js 14.x:</u> </h4>

To learn how to install Node.js [click here.](https://computingforgeeks.com/install-node-js-14-on-ubuntu-debian-linux/) 

<h5><center> OR RUN THE FOLLOWING COMMANDS in terminal</center></h5>

- sudo apt update
- curl -sL https://deb.nodesource.com/setup_14.x | sudo -E bash -
- sudo apt-get install -y nodejs

<h5><center>After Installation validate by</center></h5>

- node -v
- npm -v
------
<h4>2. <u>PhP 7.2</u>: </h4>

To learn how to install PhP [click here.](https://tecadmin.net/install-php-7-on-ubuntu/
)

<h5><center> OR RUN THE FOLLOWING COMMANDS in terminal</center></h5>

- sudo apt-get install software-properties-common
- sudo add-apt-repository ppa:ondrej/php
- sudo apt-get update
- sudo apt-get install -y php7.2

<h5><center>After Installation validate by</center></h5>

- php -v

Set the memory limit in php.ini located inside /etc/php/"version"/apache2 to atleast 512MB and restart apache

- **Note: Install the following extensions according to php version**

<h5>sudo apt-get install php7.2-mysql php7.2-curl php7.2-json php7.2-cgi php7.2-xsl php7.2-intl php7.2-gd php7.2-zip</h5>

---------
<h4> 3. <u>Apache 2.4+</u>: </h4>

To learn how to install Apache [click here.](https://www.digitalocean.com/community/tutorials/how-to-install-the-apache-web-server-on-ubuntu-18-04-quickstart)

<h5><center> OR RUN THE FOLLOWING COMMANDS in terminal</center></h5>

- sudo apt update
- sudo apt install apache2

		 Note: No firewall enabled so skip firewall steps if you are following the above linked blog.

---------------
<h4>4. <u>MySql 5.7</u>: </h4>

To learn how to install MySql [click here.](https://linuxize.com/post/how-to-install-mysql-on-ubuntu-18-04/)

<h5><center> OR RUN THE FOLLOWING COMMANDS in terminal</center></h5>

- sudo apt update
- sudo apt install mysql-server

<h5>Once the installation is completed, the MySQL service will start automatically. To check whether the MySQL server is running, type:</h5>
- sudo systemctl status mysql

#####Login to mysql client with superuser privilege for the first time

- sudo mysql

<h5>After installing mysql update your root password </h5><h5> 
If you want to login to your MySQL server as root from an external program such as phpMyAdmin.

Change the authentication method from auth_socket to mysql_native_password. You can do that by running the following command:</h5>
- ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'very_strong_ password';
- FLUSH PRIVILEGES;

####To exit the client

- \q or exit

After updating the root password you can login with the new password that you have set by the following

mysql -u 'user_name' -p 'password'

-----------
<h4>5. <u>Java JDK 8.x</u>: </h4>

To learn how to install Java 8 [click here.](https://www.digitalocean.com/community/tutorials/how-to-install-java-with-apt-on-ubuntu-18-04)

<h5><center> OR RUN THE FOLLOWING COMMANDS in terminal</center></h5>

- sudo apt update
- sudo apt install openjdk-8-jdk

<h5>Verify that this is installed with</h5>

- java -version

------------
<h4> 6. <u>Docker</u>: </h4>

To learn how to install Docker [click here.](https://www.digitalocean.com/community/tutorials/how-to-install-and-use-docker-on-ubuntu-18-04)

<h5><center> OR RUN THE FOLLOWING COMMANDS in terminal</center></h5>

- sudo apt update
- sudo apt install apt-transport-https ca-certificates curl software-properties-common

- curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -

- sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu bionic stable"

- sudo apt update
- sudo apt install docker-ce

<h5>Docker should now be installed, the daemon started, and the process enabled to start on boot. Check that it’s running:</h5>

- sudo systemctl status docker

			Note: Use `sudo` to run docker command
		
-----------
<h4> 7. <u>Postgres</u>: </h4>

- Following are the steps to install postgres and postgres-cli.

	<h5>sudo apt-get update</h5>

	<h5>sudo apt-get install postgresql postgresql-client</h5>

-----------
<h4> 8. <u>Ruby with rbenv</u>: </h4>

- Following are the steps to install rbenv:-

	<h5>sudo apt-get update</h5>

	<h5>sudo apt-get install git curl build-essential zlib1g-dev libyaml-dev libssl-dev libmysqlclient-dev libpq-dev libsqlite3-dev libreadline-dev libffi6</h5>

	<h5>git clone https://github.com/rbenv/rbenv.git ~/.rbenv</h5>

	<h5>cd ~/.rbenv && src/configure && make -C src</h5>

	<h5>echo 'export PATH="$HOME/.rbenv/bin:$HOME/.rbenv/shims:$PATH"' >> ~/.bashrc</h5>

	<h5>$HOME/.rbenv/bin/rbenv init</h5>

	<h5>source $HOME/.bashrc</h5>

	<h5>git clone https://github.com/rbenv/ruby-build.git ~/.rbenv/plugins/ruby-build</h5>

	<h5>rbenv install 2.6.1</h5>

	<h5>rbenv global 2.6.1</h5>

	<h5>cd ..</h5>

	<h5>source .bashrc</h5>

	<h5>gem install bundler</h5>

- Check Ruby version by Bundler Version.

	<h5>ruby -v</h5>

	<h5>bundle -v or bundler -v</h5>

-----------
<h4>9. <u>Passenger:</u></h4>

- Follow the step to install passenger

	1. _Installing PGP key and adding HTTPS support for APT_

  		<h5>sudo apt-get install dirmngr gnupg</h5>
  	
  		<h5>sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 561F9B9CAC40B2F7</h5>
  	
  		<h5>sudo apt-get install apt-transport-https ca-certificates</h5>
  
	2. _adding to apt repository list_

 	 	<h5>sudo sh -c 'echo deb https://oss-binaries.phusionpassenger.com/apt/passenger bionic main > /etc/apt/sources.list.d/passenger.list'</h5>
 	 
  		<h5>sudo apt-get update</h5>

	3. _Installing passenger + Apache module_

 		 <h5>sudo apt-get install libapache2-mod-passenger</h5>

  	- Note: _**apache2-dev** is required for passenger to work along with apache. Install it by the following_

 		 <h5>sudo apt-get install apache2-dev</h5>

	4. _Enabling passenger mod and Restarting apache_

  		<h5>sudo a2enmod passenger</h5>
  	
  		<h5>sudo apache2ctl restart</h5>

	5. _Check installation_

  		<h5>sudo /usr/bin/passenger-config validate-install</h5>

  		- _Note: All checks should pass. If any of the checks do not pass, please follow the suggestions on screen._

	7. _Finally, check whether Apache has started the Passenger core processes._

		- _Note- You should see Apache processes as well as Passenger processes. Do the following to check_

  		<h5>sudo /usr/sbin/passenger-memory-stats</h5>

  	Note-If you do not see any Apache processes or Passenger processes, then you probably have some kind of installation problem or configuration problem. Please refer to the troubleshooting guide on the following [link.](https://www.phusionpassenger.com/library/admin/apache/troubleshooting/)

---
<h4>10. <u>Postfix installation</u></h4>
Follow instructions from this site to use postfix https://rtcamp.com/tutorials/linux/ubuntu-postfix-gmail-smtp/

To retain the Reply-To header
https://serverfault.com/questions/811245/how-to-configure-postfix-to-force-from-field-but-leave-reply-to-alone

/etc/postfix/main.cf:

smtp_header_checks = regexp:/etc/postfix/header_check
/etc/postfix/header_check:

/From:.*/ REPLACE From: newsender@address.com
Tested with:

mail -s test -a 'Reply-To: a@domain.tld' me@me.com <<< test

<h4>11. <u>Supervisor and Redis Server:</u></h4>

---

  - These prerequisites are required for the CRM app. Install them by the following:-

  	<h5>sudo apt-get install supervisor</h5>
  	
  	<h5>sudo apt-get install redis-server</h5>

---

<div align="center">
<h4>Step 2:<u>Server Side Configuration Setup</u>:</h4>
</div>
---
1. Create oxzion and activemq users on the server:

  	<h5>sudo adduser oxzion --home /opt/oxzion --shell /usr/sbin/nologin --disabled-login</h5>
  	
  	<h5>sudo adduser activemq --home /opt/activemq --shell /usr/sbin/nologin --disabled-login</h5>
  	
  	- Note : If prompted to enter personal details like Full Name, Work, Mobile, etc, just enter Full Name as "oxzion" and leave rest of fields blank.
  	
  	<h5>sudo mkdir -p /opt/oxzion</h5>

2. Setup Environment files

	>The env folder needs to sitting in the home directory of the server i.e **$HOME/env**. Make sure the folder is already setup there before running the build script.

	- **Syntax and usage for scp command:**
		
			scp -i path_to_pem_file source_file_name username@destination_host:destination_folder

  	- Note: If you don't specify the destination folder after " : " , it will be copied in the $HOME folder itself.

  	- Note: You cannot use **sudo** to directly copy to a restricted folder in the server since sudo doesn't work with scp. You can copy the files to a desired directory in the home folder i.e $HOME, make folder and then copy files from the local machine to the server into that folder. After that you can login through ssh or use a FTP client like Filezilla or WinSCP to move files to the required location.

3. Use SCP to copy sample env files from the codebase under oxzion3.0/deployment/env to $HOME in the server. To do so, do the following:-

  	<h5>cd oxzion3.0/deployment</h5>
  	
  	<h5>scp -r -i $HOME/.ssh/oxzionapi.pem env ubuntu@x.x.x.x:<i>directory</i></h5>

---

<div align="center">
<h4>Step 4: <u>Apache and other configurations Setup</u>:</h4>
</div>

---

1. Similarly copy apache2 configuration files from the codebase under oxzion3.0/deployment/etc/apache2/sites-available to /etc/apache2/ in the server.

  	<h5>cd oxzion3.0/deployment</h5>

  	<h5>scp -r -i $HOME/.ssh/oxzionapi.pem etc/apache2/sites-available ubuntu@x.x.x.x:<i>directory</i></h5>

2. Copy the ports.conf from the codebase under oxzion3.0/deployment/etc/apache2/ to /etc/apache2/

   	<h5>cd oxzion3.0/deployment</h5>

  	<h5>scp -r -i $HOME/.ssh/oxzionapi.pem etc/apache2/sites-available ubuntu@x.x.x.x:<i>directory</i></h5>

3. You need to have the certifications setup such as oxzion.cert, intermediate.cert , amazon.key in /etc/certs on the server with appropriate permissions on the certificate files i.e chmod 444. If not do so by

  	<h5> sudo chmod 444 -R /etc/certs/* </h5>
4. You need to enable the following mods
	
	1. passenger
	2. php7.2
	3. ssl
	4. rewrite
	5. proxy
	6. proxy_http
	7. proxy_http
	8. proxy_conncet
	9. proxy_wstunnel
	
		- Run the following to enable them and then restart/reload apache.
	
		<h5>sudo a2enmod mod_name</h5>

		- For example: sudo a2enmod php7.2

---

<div align="center">
<h4>Step 4: <u>Data and Log Folder setup</u>:</h4>
</div>

---

>For Task App

1. Creation of directory where codebase will sit 

  	<h5>sudo mkdir -p /var/www/task</h5>

2. Data folder setup for task app

  	<h5>sudo mkdir -p /var/lib/oxzion/task/</h5>

3. Log folder Setup

  	<h5>sudo mkdir -p /var/log/oxzion/task</h5>

4. Creating symlink for data folder

  	<h5>sudo ln -s /var/lib/oxzion/task /var/www/task/files</h5>
  	
  	<h5>sudo ln -s /var/log/oxzion/task /var/www/task/log</h5>

5. Taking ownership of data folder as apache user

	<h5>sudo chown www-data:www-data -R /var/www/task</h5>
	
  	<h5>sudo chown www-data:www-data -R /var/lib/oxzion/task/</h5>
	
	<h5>sudo chown www-data:www-data -R /var/log/oxzion/task/</h5>

---
  >Similarly For View/UI


  <h5>sudo mkdir -p /var/lib/oxzion/vfs</h5>
  
  <h5>sudo mkdir -p /opt/oxzion/view</h5>
  
  <h5>sudo ln -s /var/lib/oxzion/vfs /opt/oxzion/view/vfs</h5>
  
  <h5>sudo chown oxzion:oxzion -R /var/lib/oxzion/vfs</h5>
  
 --- 
>For Api

  <h5>sudo mkdir -p /var/log/oxzion/api</h5>
  
  <h5>sudo mkdir -p /var/www/api/data</h5>
  
  <h5>sudo mkdir -p /var/lib/oxzion/api/cache</h5>
  
  <h5>sudo mkdir -p /var/lib/oxzion/api/uploads</h5>
  
  <h5>sudo ln -s /var/log/oxzion/api /var/www/api/logs</h5>
  
  <h5>sudo ln -s /var/lib/oxzion/api/cache /var/www/api/data/cache</h5>
  
  <h5>sudo ln -s /var/lib/oxzion/api/uploads /var/www/api/data/uploads</h5>
  
  <h5>sudo chown www-data:www-data /var/log/oxzion/api</h5>
  
  <h5>sudo chown www-data:www-data /var/lib/oxzion/api</h5>
  
---
>For Rainloop App

<h5>sudo mkdir -p /var/lib/oxzion/rainloop</h5>

<h5>sudo mkdir -p /var/www/rainloop</h5>

<h5>sudo ln -s /var/lib/oxzion/rainloop /var/www/rainloop/data</h5>

<h5>sudo chown www-data:www-data -R /var/www/rainloop</h5>

<h5>sudo chown www-data:www-data -R /var/lib/oxzion/rainloop</h5>

---
>For Calendar App
<h5>sudo mkdir -p /var/lib/oxzion/calendar</h5>

<h5>sudo mkdir -p /var/www/calendar</h5>

<h5>sudo ln -s /var/lib/oxzion/calendar /var/www/calendar/uploads</h5>

<h5>sudo chown www-data:www-data -R /var/www/calendar</h5>

<h5>sudo chown www-data:www-data -R /var/lib/oxzion/calendar</h5>

>For Crm App

<h5>sudo mkdir -p /var/www/crm</h5>

<h5>sudo mkdir -p /var/lib/oxzion/crm</h5>

<h5>sudo mkdir -p /var/log/oxzion/crm</h5>

<h5>sudo ln -s /var/lib/oxzion/crm /var/www/crm/var</h5>

<h5>sudo ln -s /var/log/oxzion/crm /var/lib/oxzion/crm/logs</h5>

<h5>sudo chown www-data:www-data -R /var/www/crm</h5>

<h5>sudo chown www-data:www-data -R /var/log/oxzion/crm</h5>

<h5>sudo chown www-data:www-data -R /var/lib/oxzion/crm</h5>
 
---
>For Chat App

<h5>sudo mkdir -p /var/log/oxzion/chat</h5>

<h5>sudo mkdir -p /var/lib/oxzion/chat</h5>

<h5>sudo mkdir -p /opt/oxzion/mattermost</h5>

<h5>sudo mkdir -p /var/lib/oxzion</h5>

<h5>sudo ln -s /var/log/oxzion/chat /opt/oxzion/mattermost/logs</h5>

<h5>sudo ln -s /var/lib/oxzion/chat /opt/oxzion/mattermost/data</h5>

<h5>sudo chown oxzion:oxzion -R /var/log/oxzion/chat</h5>

<h5>sudo chown oxzion:oxzion -R /opt/oxzion/mattermost</h5>

<h5>sudo chown oxzion:oxzion -R /var/lib/oxzion/chat</h5>

---

<div align="center">
<h4>Step 4: <u>Database Creation</u>:</h4>
</div>
---

>Basic mysql-cli tutorial

---

1. Login to mysql cli

 	 <h5>mysql -u root -p</h5>

2. Creating a User with a Password

	- Note Use the below command to list users.
  	<h5>SELECT User FROM mysql.user;</h5>
  	
  		<h5>CREATE USER 'newuser'@'%' IDENTIFIED BY 'password';</h5>

3. Creating a database.

  	<h5>Create database "dbname";</h5>

4. Granting all previleges on the database to the user.
  	<h5>GRANT ALL PRIVILEGES ON 'databasename' . * TO 'username'@'%' identified by 'password';</h5>
---

>Basic Tutotial for Postgres command-line

---
1. switch to postgres user first

	<h5>sudo su postgres</h5>

2. To run into postgres command-line interpreter

	<h5>psql</h5>

3. For help type

	<h5>help</h5>

4. To list database

	<h5>\l</h5>

6. To connect to the database

	<h5>\c  <database_name></h5>

  	- Note- For example- $ /c tasktracker

7. To see the tables

	<h5>\dt</h5>

8. To drop database

	<h5>drop database <database_name>;</h5>

  	- For example- drop database tasktracker;

9. To quit

	<h5>\q</h5>

10. After exiting switch back to default user by the following

	<h5>exit</h5>

-----

- The list of **MYSQL databases** are as follows:

	1. **oro_crm** with **crmuser** and _password_.
	
	2. **calendar** with **caluser** and _password_.
	
	3. **mattermost** with **mmuser**  and _password_.
	
	4. **oxzionapi** with **apiuser** and _password_.

- The list of **POSTGRES databases** are as follows.

	1. **tasktracker** with **taskuser** and _password_.
	
	- Follows this to create tasktracker database.
	<h5>sudo su postgres</h5>
	
		<h5>createuser -d -P taskuser</h5>
	
		<h5>createdb -O taskuser openproject</h5>
	
		<h5>exit</h5>
	
---

<div align="center">	
<h4><u>Openproject setup</u></h4>
</div>
---

- Please refer this [link](https://www.phusionpassenger.com/library/walkthroughs/deploy/ruby/ownserver/apache/oss/install_language_runtime.html) for Learn How to Deploy a Ruby App.

- Use the buildscript in the codebase under oxzion3.0/deployment to build and send it to the server. See the buildscript help to learn how to use it.

- **# Preparing Server for task app deployment.**

	1. Installations prerequisites:

		1) Install rbenv (Version Manager for Ruby)
		2) Passenger needs to be installed and configured with apache.
		3) Node and NPM
		4) Bundler Version should be 2.0.1 or +


	2. Check Ruby version by Bundler Version.

		<h5>ruby -v</h5>

		<h5>bunlde -v or bundler -v</h5>

	3. Check Node version and NPM version.

		<h5>node -v</h5>

		<h5>npm -v</h5>

	4. Create Apache Configuration

	5. Copy the "task.conf" from the codebase sitting under oxzion3.0/deployment/etc/apache2/sites-available/ to the server under /etc/apache2/sites-available/ using scp.

		- Note: If already done in Apache configurations step, skip this step.

	6. Now enable the configuration by running the following command

		<h5>sudo a2ensite task</h5>

	7. Now for the configurations to reflect changes reload or restart apache by the following commands

		<h5>sudo service apache2 restart</h5>
		
		or
		
		<h5>sudo service apache2 reload</h5>

		- Note- Apache Configuration might change according to server's ruby installation and the server application's codebase directory please refer the link given at begining to learn how to write configuration for a ruby app.

	8. Cross verify the env files of the server you are deploying sitting under  "**$HOME/env/integrations/openproject**". Update as required according to server.

		- Note: If doesn't exist please create env files first from the latest codebase before building and deploying. The env files for openproject in the codebase are under "**oxzion3.0/integrations/openproject/config**"

		- Openproject has 4 env files

			1. **config/database.yml**
	
			2. **config/configuration.yml**
	
			3. **config/local_env.yml**
	
			4. **config/secrets.yml**

			5. **config/settings.yml**

- For "**secrets.yml**" configuration you need to login inside bash shell of openproject docker and generate the secret key for production. This step should not be missed. To do so do the following:-

	- Note- Use sudo if docker command fails
		
	- Note- If the docker image is not built or doesn't exist, use the script "**buildimages.sh**" under "**oxzion3.0/deployment**" to build all the docker images in our codebase. To do so do the following:-

		1. First goto the deployment directory

			<h5>cd oxzion3.0/deployment</h5>

		2. Then run the script 

			<h5>bash buildimages.sh</h5>

		3. Finally run the following command in "oxzion3.0/integrations/openproject/"  directory to run into the bash shell of openproject docker

			<h5>docker run -it -v ${PWD}:/app -p 8095:80 openproject_build --entrypoint bash</h5>

		4. After you have entered the bash shell do the following to generate the secret key

			<h5>bundle exec rake secret RAILS_ENV=production</h5>

			- The above command will output a secret key. Copy that value to your clipboard. Next, open config/secrets.yml:

			<h5>vim config/secrets.yml</h5>

			- Note -You can whichever editor you want, like nano, etc.

			- If the file already exists, look for this:

			>production:
 		 secret_key_base: <%=ENV["SECRET_KEY_BASE"]%>

			Then replace it with the following. If the file didn't already exist, simply insert the following.

			>production:
  		secret_key_base: the value that you copied from 'rake secret'

		5. To prevent other users on the system from reading sensitive information belonging to your app, let's tighten the security on the configuration directory and the database directory:

			<h5>chmod 700 config db</h5>
	
			<h5>chmod 600 config/database.yml config/secrets.yml</h5>

- **Database Setup:**

	1. You need postgres client in the server first. To check if postgres is already there do the following\

		<h5>psql -V</h5>

	2. If postgres is not installed, follow the Server Side Software Setup.

	3. After installation follow the database setup section to create database for task app.

- Note- The database configuration should be updated in the _**database.yml**_ accordingly to the database you have created.

---

<div align="center">
<h4><u>First time installation steps for Task App</u>:</h4>
</div>

---
1. Use the deploy script in the server to deploy the application. Do so by the following commands

	<h5>cd $HOME/oxzion3.0/deployment</h5>

	<h5>sudo bash deploy.sh</h5>

2. After deployment is over go to the temp directory "**$HOME/oxzion3.0/temp/integrations/openproject**" and run the db seed command.

	- Note: **db seed needs to be run only for the first time** to push some default data to the database. To do so do the following

	<h5>cd $HOME/oxzion3.0/temp/integrations/openproject</h5>

3. Running the db seed command

	<h5>sudo mkdir -p files/attachment </h5>

	<h5>sudo chown -R $USER:$USER .</h5>

	<h5>bundle exec rake db:seed RAILS_ENV=production</h5>

	- Note: If **db:seed fails** cross verify the previous steps.

4. Now copy the task app to the designated location that is "**/var/www/task**" by the following command

	<h5>sudo rsync -rl --delete . /var/www/task/</h5>

5. Now run a chown to the codebase and data folder directory once more just to make sure all the modified files are owned by the the apache user. Do so by the following command.

	<h5>sudo chown www-data:www-data -R /var/www/task</h5>

	<h5>sudo chown www-data:www-data -R /var/lib/oxzion/task/</h5>

6. For Subsequent deployment just run the deploy script as usual in the server under oxzion3.0/deployment. To do so, run the following
	
	<h5>sudo bash deploy.sh</h5>

---

<div align="center">	
<h4>Activemq setup</h4>
</div>

---

1. Download the activemq tar.gz file using curl.

	<h5>curl "https://archive.apache.org/dist/activemq/5.15.6/apache-activemq-5.15.6-bin.tar.gz" -o apache-activemq-5.15.6-bin.tar.gz</h5>

	<h5>sudo tar xzf apache-activemq-5.15.6-bin.tar.gz -C  /opt</h5>

	<h5>sudo ln -s /opt/apache-activemq-5.15.6 /opt/activemq</h5>

	<h5>sudo useradd -r -M -d /opt/activemq activemq</h5>

	<h5>sudo chown -R activemq:activemq /opt/apache-activemq-5.15.6</h5>
	
	<h5> sudo adduser activemq --home /opt/activemq --shell /usr/sbin/nologin --disabled-login</h5>
	
	<h5>sudo chown -RH activemq:activemq /opt/activemq</h5>


2. Copy the activemq.service to /etc/systemd/system/activemq.service and restart systemctl by the following

	<h5>sudo systemctl daemon-reload</h5>

---

<div align="center">	
<h4>Camel setup</h4>
</div>
---
<h5> sudo mkdir -p /opt/oxzion/camel</h5>

<h5>sudo chown oxzion:oxzion -R /opt/oxzion/camel</h5>


-------------------------



---

<div align="center">	
<h4>OroCRM setup</h4>
</div>
---

1. Create a database  **_oro_crm_** with a user **_crmuser_** and granting all previleges to the user in database.

	<h5>sudo mysql -u root -p</h5>

	<h5>mysql>create database oro_crm;</h5>

	<h5>mysql>CREATE USER 'crmuser'@'localhost' IDENTIFIED BY '<password>';</h5>

	<h5>mysql>GRANT ALL PRIVILEGES ON `oro_crm` . * TO 'crmuser'@'localhost' identified by '<password>';</h5>

2. For a **First time setup**  the following commands should be performed  in order to install the application and also to do a database migration.
 
	- Note: You should be in the oxzion3.0 root folder to perform the commands below

	- Note: Ensure the Installed flag in parameters.yml under env folder is initially set to false before you start your build

		<h5>sudo rm -R temp</h5>
		
		<h5>mkdir temp</h5>
		
		<h5>unzip build.zip -d temp</h5>
		
		<h5>cd temp/integrations/crm</h5>
		
		- Note : Check parameters.yml after unzip check installed is set as false before doing oro:install. Installation will fail if not changed.
		
		<h5>php bin/console oro:install --env=prod --timeout=30000 --application-url="http://localhost:8075/crm/public" --organization-name="Vantage Agora" --user-name="admin" --user-email="admin@example.com"</h5>
		
		- Note - Update the flags as required for the above command like application url and user-email.

	- Note: If it fails for some reason start with an empty database and remove the cache folder

		<h5>rm -R var/cache/prod/*</h5>
		<h5>sudo service apache2 restart</h5>

3. After the migration is completed replace the parameters.yml in the env folder from the **/var/www/crm/config** folder on the instance after deployment

	<h5>sudo cp config/parameters.yml /var/www/crm/config/</h5>
	
	<h5>sudo chown www-data:www-data /var/www/crm/config/parameters.yml</h5>
	
	<h5>cp config/parameters.yml ~/env/integrations/orocrm/config/</h5>

	<h5>sudo apt install php7.2-opcache</h5>
	
	<h5>sudo phpenmod opcache</h5>

---

<div align="center">	
<h4>Mattermost setup</h4>
</div>
---

- Create a database **_mattermost_** with user **_mmuser_** and granting all previleges to the user to the database.

- Change configurations for the database in **_mattermost/mattermost-server/config/default.json_** under **_SqlSettings_**.

---

<div align="center">	
<h4>Calendar setup</h4>
</div>
---

- Create calendar database by executing **_pec_db_mysql.sql_** dump file from **_eventcalendar/SampleEventDB_** in MySQL.


---

<div align="center">	
<h3>Extras</h3>
</div>
---

- To start a service

	<h5>sudo systemctl daemon-reload</h5>

	<h5>sudo systemctl enable my-webapp.service</h5>

	<h5>sudo systemctl start my-webapp</h5>

	<h5>sudo systemctl status my-webapp</h5>


- To Learn how to make a service [click here.](https://dzone.com/articles/run-your-java-application-as-a-service-on-ubuntu)
