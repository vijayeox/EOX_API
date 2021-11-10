#!/bin/bash
#This script is used to deploy build.zip to respective folders
# exit when any command fails
#set -e
#trap 'echo "\"${BASH_COMMAND}\" command failed with exit code $?."' EXIT
#going back to oxzion3.0 root directory
cd ../
echo "This is the present working directory ---->${PWD}"
#Defining variables for later use
homedir=${PWD}
RED="\e[91m"
GREEN="\e[92m"
BLUE="\e[34m"
YELLOW="\e[93m"
MAGENTA="\e[35m"
BLUEBG="\e[44m"
CYAN="\e[36m"
BLINK="\e[5m"
INVERT="\e[7m"
RESET="\e[0m"

#Checking if temp folder exist, delete and create new.
if [ -d "./temp" ] ;
then
    echo -e "${RED}Directory temp exist!${RESET}"
    echo -e "${YELLOW}Deleting existing 'temp' folder to avoid conflict...\n${RESET}"    
    rm -Rf temp
fi

#WRITING FUNCTIONS FOR DIFFERENT TASKS#
unpack()
{   echo "this is homedir ---> ${homedir}"
    cd ${homedir}
    echo -e "${YELLOW}Extracting build zip to './temp' folder...${RESET}"
    mkdir -p temp
    unzip build.zip -d temp
    echo -e "${GREEN}Extracting build zip to 'temp' folder Completed Successfully!\n${RESET}"
    cd temp
    TEMP=${PWD}
}
api()
{   
    echo "this is temp dir ---> ${TEMP}"
    cd ${TEMP}
    echo -e "${YELLOW}Copying API...${RESET}"
    if [ ! -d "./api/v1" ] ;
    then
        echo -e "${RED}API was not was not packaged so skipping it\n${RESET}"
    else    
        #making the directory where api will be copied.
        #moving to temp directory and copying required
        echo -e "${YELLOW}Stopping Apache${RESET}"
        service apache2 stop
        cd ${TEMP}
        rsync -rl api/v1/data/uploads/ /var/www/api/data/uploads/
        rsync -rl --delete api/v1/data/eoxapps/ /var/lib/oxzion/api/eoxapps/
        rsync -rl --delete api/v1/data/migrations/ /var/lib/oxzion/api/migrations/
        rsync -rl api/v1/data/template/ /var/lib/oxzion/api/template/
        rm -Rf api/v1/data/uploads
        rm -Rf api/v1/data/cache
        rm -Rf api/v1/data/delegate
        rm -Rf api/v1/data/forms
        rm -Rf api/v1/data/eoxapps
        rm -Rf api/v1/data/import
        rm -Rf api/v1/data/migrations
        rm -Rf api/v1/data/template
        rm -Rf api/v1/data/file_docs
        rm -Rf api/v1/data/AppDeploy
        rm -Rf api/v1/data/AppSource
        rm -Rf api/v1/data/pages
        rm -Rf api/v1/data/entity
        rsync -rl --delete api/v1/ /var/www/api/
        ln -nfs /var/lib/oxzion/api/cache /var/www/api/data/cache
        ln -nfs /var/lib/oxzion/api/uploads /var/www/api/data/uploads
        ln -nfs /var/lib/oxzion/api/delegate /var/www/api/data/delegate
        ln -nfs /var/lib/oxzion/api/forms /var/www/api/data/forms
        ln -nfs /var/lib/oxzion/api/eoxapps /var/www/api/data/eoxapps
        ln -nfs /var/lib/oxzion/api/file_docs /var/www/api/data/file_docs
        ln -nfs /var/lib/oxzion/api/import /var/www/api/data/import
        ln -nfs /var/lib/oxzion/api/migrations /var/www/api/data/migrations
        ln -nfs /var/lib/oxzion/api/template /var/www/api/data/template
        ln -nfs /var/lib/oxzion/api/AppDeploy /var/www/api/data/AppDeploy
        ln -nfs /var/lib/oxzion/api/AppSource /var/www/api/data/AppSource
        ln -nfs /var/lib/oxzion/api/pages /var/www/api/data/pages
        ln -nfs /var/lib/oxzion/api/entity /var/www/api/data/entity
        ln -nfs /var/log/oxzion/api /var/www/api/logs
        chown www-data:www-data -R /var/www/api
        echo -e "${GREEN}Copying API Complete!\n${RESET}"
        echo -e "${YELLOW}Starting migrations script for API${RESET}"
        cd /var/www/api
        ./migrations migrate
        echo -e "${GREEN}Migrations Complete!${RESET}"
        echo -e "${GREEN}Starting Apache${RESET}"
        service apache2 start
        service php7.2-fpm reload
    fi    
}
api2()
{   
    echo "this is temp dir ---> ${TEMP}"
    cd ${TEMP}
    echo -e "${YELLOW}Copying API...${RESET}"
    if [ ! -d "./api2/v1" ] ;
    then
        echo -e "${RED}API was not was not packaged so skipping it\n${RESET}"
    else    
        #making the directory where api will be copied.
        #moving to temp directory and copying required
        echo -e "${YELLOW}Stopping Apache${RESET}"
        service apache2 stop
        cd ${TEMP}
        rsync -rl api2/v1/data/uploads/ /var/www/oxzion2/api/data/uploads/
        rsync -rl --delete api2/v1/data/eoxapps/ /var/lib/oxzion2/api/eoxapps/
        rsync -rl --delete api2/v1/data/migrations/ /var/lib/oxzion2/api/migrations/
        rsync -rl api2/v1/data/template/ /var/lib/oxzion2/api/template/
        rm -Rf api2/v1/data/uploads
        rm -Rf api2/v1/data/cache
        rm -Rf api2/v1/data/delegate
        rm -Rf api2/v1/data/forms
        rm -Rf api2/v1/data/eoxapps
        rm -Rf api2/v1/data/import
        rm -Rf api2/v1/data/migrations
        rm -Rf api2/v1/data/template
        rm -Rf api2/v1/data/file_docs
        rm -Rf api2/v1/data/AppDeploy
        rm -Rf api2/v1/data/AppSource
        rm -Rf api2/v1/data/pages
        rm -Rf api2/v1/data/entity
        rsync -rl --delete api2/v1/ /var/www/oxzion2/api/
        ln -nfs /var/lib/oxzion2/api/cache /var/www/oxzion2/api/data/cache
        ln -nfs /var/lib/oxzion2/api/uploads /var/www/oxzion2/api/data/uploads
        ln -nfs /var/lib/oxzion2/api/delegate /var/www/oxzion2/api/data/delegate
        ln -nfs /var/lib/oxzion2/api/forms /var/www/oxzion2/api/data/forms
        ln -nfs /var/lib/oxzion2/api/eoxapps /var/www/oxzion2/api/data/eoxapps
        ln -nfs /var/lib/oxzion2/api/file_docs /var/www/oxzion2/api/data/file_docs
        ln -nfs /var/lib/oxzion2/api/import /var/www/oxzion2/api/data/import
        ln -nfs /var/lib/oxzion2/api/migrations /var/www/oxzion2/api/data/migrations
        ln -nfs /var/lib/oxzion2/api/template /var/www/oxzion2/api/data/template
        ln -nfs /var/lib/oxzion2/api/AppDeploy /var/www/oxzion2/api/data/AppDeploy
        ln -nfs /var/lib/oxzion2/api/AppSource /var/www/oxzion2/api/data/AppSource
        ln -nfs /var/lib/oxzion2/api/pages /var/www/oxzion2/api/data/pages
        ln -nfs /var/lib/oxzion2/api/entity /var/www/oxzion2/api/data/entity
        ln -nfs /var/log/oxzion2/api /var/www/oxzion2/api/logs
        chown www-data:www-data -R /var/www/oxzion2/api
        echo -e "${GREEN}Copying API Complete!\n${RESET}"
        echo -e "${YELLOW}Starting migrations script for API${RESET}"
        cd /var/www/oxzion2/api
        ./migrations migrate
        echo -e "${GREEN}Migrations Complete!${RESET}"
        echo -e "${GREEN}Starting Apache${RESET}"
        service apache2 start
        service php7.2-fpm reload
    fi    
}
camel()
{   
    cd ${TEMP}
    echo -e "${YELLOW}Copying Camel...${RESET}"
    if [ ! -d "./integrations/camel" ] ;
    then
        echo -e "${RED}CAMEL was not packaged so skipping it\n${RESET}"
    else
        #making the directory where api will be copied.
        echo -e "${GREEN}Stopping Camel service${RESET}"
        systemctl stop camel
        echo -e "${YELLOW}Stopped!${RESET}"
        #moving to temp directory and copying required
        cd ${TEMP}
        rsync -rl --delete integrations/camel/ /opt/oxzion/camel/
        chown -R oxzion:oxzion /opt/oxzion/camel
        echo -e "${GREEN}Copying Camel Complete!\n${RESET}"
        echo -e "${YELLOW}Starting Camel service${RESET}"
        systemctl start camel
        echo -e "${GREEN}Started!${RESET}"
    fi    
}

#Function to copy calendar
calendar()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EventCalendar..${RESET}"
    if [ ! -d "./integrations/eventcalendar" ] ;
    then
        echo -e "${RED}CALENDAR was not packaged so skipping it\n${RESET}"
    else
        cd ${TEMP}
        rm -Rf integrations/eventcalendar/uploads
        rsync -rl --delete integrations/eventcalendar/ /var/www/calendar/
        ln -nfs /var/lib/oxzion/calendar /var/www/calendar/uploads
        chown www-data:www-data -R /var/www/calendar
        echo -e "${GREEN}Copying EventCalendar Complete!${RESET}"
    fi
}
#Function to copy mattermost
mattermost()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying Mattermost..${RESET}"
    if [ ! -d "./integrations/mattermost" ] ;
    then
        echo -e "${RED}MATTERMOST was not packaged so skipping it\n${RESET}"
    else
        echo -e "${YELLOW}Backing up current configurations${RESET}"
        cp /opt/oxzion/mattermost/config/config.json ${TEMP}/integrations/mattermost/config/config.json
        cp /opt/oxzion/mattermost/config/config.json /home/ubuntu/env/integrations/mattermost/mattermost-server/config/config.json
        echo -e "${GREEN}Backing up current configurations completed${RESET}"
        echo -e "${GREEN}Stopping Mattermost service${RESET}"
        systemctl stop mattermost
        echo -e "${YELLOW}Stopped!${RESET}"
        cd ${TEMP}
        rm -Rf integrations/mattermost/logs
        rm -Rf integrations/mattermost/data
        rsync -rl integrations/mattermost/ /opt/oxzion/mattermost/
        ln -nfs /var/lib/oxzion/chat /opt/oxzion/mattermost/data
        ln -nfs /var/log/oxzion/chat /opt/oxzion/mattermost/logs
        chown oxzion:oxzion -R /opt/oxzion/mattermost
        echo -e "${GREEN}Copying Mattermost Complete!${RESET}"
        echo -e "${GREEN}Starting Mattermost service${RESET}"
        systemctl start mattermost
        echo -e "${YELLOW}Started!${RESET}"
    fi
}
#Function to copy OROcrm
orocrm()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying CRM..${RESET}"
    if [ ! -d "./integrations/crm" ] ;
    then
        echo -e "${RED}CRM was not packaged so skipping it\n${RESET}"
    else    
        cd ${TEMP}
        echo -e "${YELLOW}Stopping apache and supervisor service${RESET}"
        service apache2 stop
        systemctl stop supervisor
        mkdir -p /var/www/crm
        chown ubuntu:ubuntu -R integrations/crm
        echo -e "${YELLOW}Installing composer libraries in ${CYAN}crm/vendor${RESET}"
        runuser -l ubuntu -c "composer install -d ${TEMP}/integrations/crm"
        echo -e "${YELLOW}Installing node_modules in ${CYAN}crm/build${RESET}"
        runuser -l ubuntu -c "npm install --prefix ${TEMP}/integrations/crm/build"
        echo -e "${YELLOW}Installing Assets for CRM now${RESET}"
        runuser -l ubuntu -c "php ${TEMP}/integrations/crm/bin/console oro:assets:install"
        echo -e "${YELLOW}Loading migrations now${RESET}"
        runuser -l ubuntu -c "php ${TEMP}/integrations/crm/bin/console oro:migration:load --force"
        mkdir -p integrations/crm/public/css/themes/oro/bundles/bowerassets/font-awesome
        rsync -rl --delete integrations/crm/public/bundles/bowerassets/font-awesome/ integrations/crm/public/css/themes/oro/bundles/bowerassets/font-awesome/
        if [ -d "/var/www/crm/var" ] || [ ! -L "/var/www/crm/var" ] ;
        then
            echo -e "${YELLOW}Stopping cron service${RESET}"
            service cron stop
            if [ -d "/var/www/crm/var" ] ;
            then
                rsync /var/www/crm/var/ /var/lib/oxzion/crm/
                rm -rf /var/www/crm/var
                chown www-data:www-data -R /var/lib/oxzion/crm
            fi
            #check for link exists
            if [ ! -L "/var/www/crm/var" ] ;
            then
                echo -e "${YELLOW}Links doesn't exist. Creating links now"
                ln -nfs /var/lib/oxzion/crm /var/www/crm/var
                ln -nfs /var/log/oxzion/crm /var/lib/oxzion/crm/logs    
            fi
            echo -e "${YELLOW}Starting cron service${RESET}"
            service cron start
        fi            
        rm -Rf integrations/crm/var
        echo -e "${YELLOW}Copying existing link${RESET}"
        cp -P /var/www/crm/var integrations/crm/var 
        rsync -rl --delete integrations/crm/ /var/www/crm/
        chown www-data:www-data -R /var/lib/oxzion/crm
        rsync -rl --delete /var/www/crm/orocrm_supervisor.conf /etc/supervisor/conf.d/
        echo -e "${GREEN}Copying CRM Complete!${RESET}"
        chown www-data:www-data -R /var/www/crm
        rm -Rf /var/www/crm/var/cache/*
        echo -e "${YELLOW}Updating current logs with timestamp${RESET}"
        mv /var/log/oxzion/crm/prod.log /var/log/oxzion/crm/prod_`date +%d-%m-%y_%H%M`.log
        echo -e "${YELLOW}Starting apache and supervisor service${RESET}"
        systemctl start supervisor
        service apache2 start
    fi
}
#Function to copy rainloop
rainloop()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying Rainloop..${RESET}"
    if [ ! -d "./integrations/rainloop" ] ;
    then
        echo -e "${RED}RAINLOOP was not packaged so skipping it\n${RESET}"
    else
        cd ${TEMP}
        service apache2 stop
        rsync -rl integrations/rainloop/data/ /var/www/rainloop/data/
        rm -Rf integrations/rainloop/data
        rsync -rl --delete integrations/rainloop/ /var/www/rainloop/
        ln -nfs /var/lib/oxzion/rainloop /var/www/rainloop/data
        chown www-data:www-data -R /var/www/rainloop
        chown www-data:www-data -R /var/lib/oxzion/rainloop
        echo -e "${GREEN}Copying Rainloop Complete!${RESET}"
        service apache2 start
    fi
}
view()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying view...${RESET}"
    if [ ! -d "./view" ] ;
    then
        echo -e "${RED}VIEW was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        echo -e "${YELLOW}Stopped!${RESET}"
        cd ${TEMP}
        rsync -rl view/vfs/ /opt/oxzion/view/vfs/
        rm -Rf view/vfs
        unlink /opt/oxzion/view/vfs
        find -L /opt/oxzion/view/apps/ -maxdepth 1 -xtype l -exec cp -P "{}" /home/ubuntu/oxzion3.0/temp/view/apps/  \;
        find -L /opt/oxzion/view/themes/ -maxdepth 1 -xtype l -exec cp -P "{}" /home/ubuntu/oxzion3.0/temp/view/themes/  \;
        find -L /opt/oxzion/view/gui/src/externals/ -maxdepth 1 -xtype l -exec cp -P "{}" /home/ubuntu/oxzion3.0/temp/view/gui/src/externals/  \;
        rsync -rl --delete view/ /opt/oxzion/view/
        ln -nfs /var/lib/oxzion/vfs /opt/oxzion/view/vfs
        chown www-data:www-data -R /opt/oxzion/view/vfs
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/view
        echo -e "${GREEN}Copying view Complete!${RESET}"
        echo -e "${GREEN}Starting view service${RESET}"
        chmod 777 -R /opt/oxzion/view/bos
        chmod 777 /opt/oxzion/view/apps
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
view2()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying view...${RESET}"
    if [ ! -d "./view2" ] ;
    then
        echo -e "${RED}VIEW was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view2
        echo -e "${YELLOW}Stopped!${RESET}"
        cd ${TEMP}
        rsync -rl view2/vfs/ /opt/oxzion2/view/vfs/
        rm -Rf view2/vfs
        unlink /opt/oxzion2/view/vfs
        find -L /opt/oxzion2/view/apps/ -maxdepth 1 -xtype l -exec cp -P "{}" /home/ubuntu/oxzion3.0/temp/view2/apps/  \;
        find -L /opt/oxzion2/view/themes/ -maxdepth 1 -xtype l -exec cp -P "{}" /home/ubuntu/oxzion3.0/temp/view2/themes/  \;
        rsync -rl --delete view2/ /opt/oxzion2/view/
        ln -nfs /var/lib/oxzion2/vfs /opt/oxzion2/view/vfs
        chown www-data:www-data -R /opt/oxzion2/view/vfs
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion2/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion2/view
        echo -e "${GREEN}Copying view Complete!${RESET}"
        echo -e "${GREEN}Starting view service${RESET}"
        chmod 777 -R /opt/oxzion2/view/bos
        chmod 777 /opt/oxzion2/view/apps
        systemctl start view2
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
workflow()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying workflow...${RESET}"
    if [ ! -d "./integrations/workflow" ] ;
    then
        echo -e "${RED}Workflow was not packaged so skipping it\n${RESET}"
    else
        service camunda stop
        cd ${TEMP}
        cp ${TEMP}/integrations/workflow/identity_plugin-1.0.jar integrations/workflow/processengine_plugin-1.0.jar /opt/oxzion/camunda/lib/
        cp ${TEMP}/integrations/workflow/bpm-platform.xml /opt/oxzion/camunda/conf/
        chown oxzion:oxzion -R /opt/oxzion/camunda
        echo -e "${GREEN}Copying workflow Complete!${RESET}"
        service camunda start
    fi
}
openproject()
{
    OLDPATH=$PATH
    export PATH="/home/ubuntu/.nodenv/shims:/home/ubuntu/.rbenv/shims:$PATH"
    cd ${TEMP}
    echo -e "${YELLOW}Copying openproject...${RESET}"
    if [ ! -d "./integrations/openproject" ] ;
    then
        echo -e "${RED}Openproject was not packaged so skipping it\n${RESET}"
    else
        cd ${TEMP}/integrations/openproject
        ln -nfs /var/log/oxzion/task ./log
        ln -nfs /var/lib/oxzion/task ./files
        echo -e "${YELLOW}Running db migrate now...${RESET}"
        bundle exec rake db:migrate RAILS_ENV=production
        cd /var/www/task
        #bundle exec rake jobs:work RAILS_ENV=production
        echo -e "${YELLOW}Copying codebase now...${RESET}"
        rsync -rl --delete ${TEMP}/integrations/openproject/ /var/www/task/
        echo -e "${YELLOW}Copying openproject Completed...${RESET}"
        chown www-data:www-data -R /var/www/task
    fi
    export PATH=$OLDPATH
}
helpapp()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying HelpApp...${RESET}"
    if [ ! -d "./integrations/help" ] ;
    then
        echo -e "${RED}HelpApp was not packaged so skipping it\n${RESET}"
    else
        echo -e "${YELLOW}Stopping Apache...${RESET}"
        sudo service apache2 stop
        echo -e "${YELLOW}Copying HelpApp...${RESET}"
        rsync -rl --delete ${TEMP}/integrations/help/ /var/www/help/
        echo -e "${YELLOW}Copying HelpApp Completed...${RESET}"
        sudo service apache2 start
        echo -e "${YELLOW}Starting Apache...${RESET}"
    fi
}
#on-hold
edms()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EDMS...${RESET}"
    if [ ! -d "./integrations/edms" ] ;
    then
        echo -e "${RED}EDMS was not packaged so skipping it\n${RESET}"
    else
        cd ${TEMP}/integrations/edms/mayan
        echo -e "${YELLOW}Creating edms data folder...${RESET}"
        ln -nfs /var/lib/oxzion/edms ./media
        rsync -rl --delete ${TEMP}/integrations/edms/ /var/www/edms/
        echo -e "${YELLOW}Taking ownership as www-data...${RESET}"
        chown www-data:www-data -R /var/www/edms
        chown www-data:www-data -R /var/lib/oxzion/edms
        echo -e "${GREEN}Copying edms Complete!${RESET}"
    fi
}
diveinsurance()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/DiveInsurance" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./DiveInsurance /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building DiveInsurance apps using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/DiveInsurance'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/apps/DiveInsurance/
        rm -rf /opt/oxzion/view/apps/DiveInsurance/node_modules
        npm install --unsafe-perm
        npm run build
        cd /opt/oxzion/view/themes/VicenciaAndBuckleyTheme/
        rm -rf /opt/oxzion/view/themes/VicenciaAndBuckleyTheme/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        chown www-data:www-data -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chmod 777 -R /opt/oxzion/eoxapps
        systemctl start view
        service php7.2-fpm reload
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
insurancemanagement()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/InsuranceManagement" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./InsuranceManagement /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building InsuranceManagement apps using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/InsuranceManagement'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chown www-data:www-data -R /opt/oxzion/view
        chmod 777 -R /opt/oxzion/eoxapps
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
insuranceoi()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/InsuranceOI" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./InsuranceOI /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building InsuranceOI app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/InsuranceOI'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/Insurance/
        rm -rf /opt/oxzion/view/apps/Insurance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
task()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/Task" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./Task /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building Task apps using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/Task'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chown www-data:www-data -R /opt/oxzion/view
        chmod 777 -R /opt/oxzion/eoxapps
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
bridgemed()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/BridgeMed" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./BridgeMed /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building BridgeMed apps using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/BridgeMed'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/apps/BridgeMed/
        rm -rf /opt/oxzion/view/apps/BridgeMed/node_modules
        npm install --unsafe-perm
        npm run build
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chown www-data:www-data -R /opt/oxzion/view
        chmod 777 -R /opt/oxzion/eoxapps
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
finance()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/Finance" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./Finance /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building Finance app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/Finance'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/Finance/
        rm -rf /opt/oxzion/view/apps/Finance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
transportation()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/Transportation" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./Transportation /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building Transportation app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/Transportation'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/Transportation/
        rm -rf /opt/oxzion/view/apps/Transportation/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
arrowhead()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/ArrowHead" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./ArrowHead /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building ArrowHead app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/ArrowHead'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/ArrowHead/
        rm -rf /opt/oxzion/view/apps/ArrowHead/node_modules
        npm install --unsafe-perm
        npm run build
        cd /opt/oxzion/view/themes/ArrowHeadTheme/
        rm -rf /opt/oxzion/view/themes/ArrowHeadTheme/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}

hubdrive()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/hubdrive" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./hubdrive /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building hubdrive app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/hubdrive'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/hubdrive/
        rm -rf /opt/oxzion/view/apps/hubdrive/node_modules
        npm install --unsafe-perm
        npm run build
        cd /opt/oxzion/view/themes/hubdriveTheme/
        rm -rf /opt/oxzion/view/themes/hubdriveTheme/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}












covid()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/Covid19" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./Covid19 /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building Covid 19 app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/Covid19'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/Covid19CheckList/
        rm -rf /opt/oxzion/view/apps/Covid19CheckList/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        service php7.2-fpm reload
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
axon()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/AXON" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./AXON /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building AXON app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/AXON'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/AXONInsurance/
        rm -rf /opt/oxzion/view/apps/AXONInsurance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        service php7.2-fpm reload
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
riscom()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/RISCOM" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./RISCOM /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building RISCOM app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/RISCOM'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/RISCOMInsurance/
        rm -rf /opt/oxzion/view/apps/RISCOMInsurance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        service php7.2-fpm reload
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
biofi()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/Biofi" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./Biofi /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building Biofi app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/Biofi'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/BiofiFinance/
        rm -rf /opt/oxzion/view/apps/BiofiFinance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        service php7.2-fpm reload
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
tennant()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/Tennant" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./Tennant /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building Tennant app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/Tennant'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/TennantInsurance/
        rm -rf /opt/oxzion/view/apps/TennantInsurance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        service php7.2-fpm reload
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
bsri()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/BSRI" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./BSRI /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building BSRI app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/BSRI'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/BSRIInsurance/
        rm -rf /opt/oxzion/view/apps/BSRIInsurance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        service php7.2-fpm reload
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
hiig()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/HIIG" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        systemctl stop view
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./HIIG /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building HIIG app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/HIIG'
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/HIIGInsurance/
        rm -rf /opt/oxzion/view/apps/HIIGInsurance/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        service php7.2-fpm reload
        systemctl start view
        echo -e "${YELLOW}Started view service!${RESET}"
    fi
}
appbuilder()
{
    cd ${TEMP}
    echo -e "${YELLOW}Copying EOX apps...${RESET}"
    if [ ! -d "./clients/EOXAppBuilder" ] ;
    then
        echo -e "${RED}EOX Apps was not packaged so skipping it\n${RESET}"
    else
        echo -e "${GREEN}Stopping view service${RESET}"
        cd ${TEMP}/clients
        echo -e "${YELLOW}Copying EOX Apps to /opt/oxzion/eoxapps directory${RESET}"
        mkdir -p /opt/oxzion/eoxapps
        rsync -rl --delete ./EOXAppBuilder /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        echo -e "${YELLOW}Building EOXAppBuilder app using deployapp API${RESET}"
        jwt=$(curl --location --request POST 'http://localhost:8080/auth' --form 'username=admintest' --form 'password=Welcome2eox!' 2>/dev/null | jq -r '.data.jwt')
        curl --location --request POST 'http://localhost:8080/app/deployapp' -H 'Authorization: Bearer '${jwt}'' -F 'path=/opt/oxzion/eoxapps/EOXAppBuilder'
        systemctl stop view
        echo -e "${YELLOW}Copying EOX Apps directory Complete!${RESET}"
        service php7.2-fpm reload
        echo -e "${GREEN}Building and Running package discover in bos${RESET}"
        cd /opt/oxzion/view/bos/
        npm run build
        npm run package:discover
        chown www-data:www-data -R /opt/oxzion/eoxapps
        chmod 777 -R /opt/oxzion/eoxapps
        cd /opt/oxzion/view/apps/EOXAppBuilder/
        rm -rf /opt/oxzion/view/apps/EOXAppBuilder/node_modules
        npm install --unsafe-perm
        npm run build
        cd /opt/oxzion/view/themes/EOXAppbuilderTheme/
        rm -rf /opt/oxzion/view/themes/EOXAppbuilderTheme/node_modules
        npm install --unsafe-perm
        npm run build
        chown www-data:www-data -R /opt/oxzion/view
        systemctl start view
        service php7.2-fpm restart
        echo -e "${YELLOW}Started view service and restarted fpm!${RESET}"
    fi
}
#calling functions accordingly
unpack
echo -e "${YELLOW}Now copying files to respective locations..${RESET}"
api
api2
view
view2
echo -e "${CYAN}Copying Integrations Now...\n${RESET}"
camel
calendar
mattermost
orocrm
rainloop
openproject
diveinsurance
insurancemanagement
insuranceoi
task
bridgemed
finance
workflow
helpapp
transportation
arrowhead
axon
covid
riscom
biofi
tennant
bsri
hiig
appbuilder
#edms
echo -e "${GREEN}${BLINK}DEPLOYED SUCCESSFULLY${RESET}"
