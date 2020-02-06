#!/bin/bash
#Script to deploy hub to server
#going back to oxzion3.0 root directory
start_time="$(date +%s)"
cd ../
#Defining variables for later use
#pass second parameter as server u want to build for example abc@xyz.com or abc@1.1.1.1
SERVER=${2}
#pass third parameter as the path to the identity file(pem/ppk) in your local system.
PEM=${3}
OXHOME=${PWD}
RED="\e[91m"
GREEN="\e[92m"
BLUE="\e[34m"
YELLOW="\e[93m"
MAGENTA="\e[35m"
BLUEBG="\e[44m"
CYAN="\e[96m"
BLINK="\e[5m"
INVERT="\e[7m"
RESET="\e[0m"

#help function to print help message
buildhelp()
{
    echo -e "1.  all             -${YELLOW}For packaging everything.${RESET}"
    echo -e "2.  api             -${YELLOW}For packaging API.${RESET}"
    echo -e "3.  view            -${YELLOW}For packaging UI/View.${RESET}"
    echo -e "4.  workflow        -${YELLOW}For packaging workflow.${RESET}"
    echo -e "5.  camel           -${YELLOW}For packaging Apache Camel.${RESET}"
    echo -e "6.  diveinsurance   -${YELLOW}For packaging diveinsurance app.${RESET}"
    echo -e "7.  task            -${YELLOW}For packaging task app.${RESET}"
    echo -e "8.  bridgemed       -${YELLOW}For packaging bridgemed app.${RESET}"
    echo -e "9.  integrations    -${YELLOW}For packaging all integrations.${RESET}"
    echo -e "10. --help or -h    -${YELLOW}For help.${RESET}"
    echo -e "11. list            -${YELLOW}For list of options.${RESET}"
    echo -e "12. deploy          -${YELLOW}For deploying to production${RESET}"
    echo -e "13. clean           -${YELLOW}For cleaning the production server${RESET}"
    echo -e "14. setup           -${YELLOW}For fresh setup of the production server${RESET}"
    echo -e "15. package         -${YELLOW}For packaging existing build${RESET}"
}
#checking if no arguments passed. Give error and exit.
if [ $# -eq 0 ] ;
#if [ -z "$1" ] || [ -z "$2" ];
then
    echo -e "${RED}ERROR: argument missing.${RESET}"
    echo -e "$0 : needs 3 arguments to start."
    echo -e "For example type \n$ ${GREEN}build.sh calendar${YELLOW}(build option) ${GREEN}abc@xyz.com${YELLOW}(server name){GREEN}~/.ssh/abc.pem${YELLOW}(identity file path)${RESET}.\nSee build option list below."
    echo -e "Type '$0 --help' or '$0 -h' for more information."
    echo -e "${BLUEBG}Argument list:${RESET}"
    buildhelp
    exit 0
fi
#writing functions for different tasks
#function checking exiting build dir and deleting it
check_dir()
{
cd ${OXHOME}
if [ -d "./build" ] ;
then
    echo -e "${RED}Directory build exist!${RESET}"
    echo -e "${YELLOW}Deleting existing build folder to avoid conflict...${RESET}"    
    rm -Rf build
fi
}
package()
{
    #going back to /build directory
    cd ${OXHOME}/build
    # zip the contents of the build folder excluding node_modules
    echo -e "${YELLOW}${BLINK}Packaging /build to build.zip${RESET}"
    if [ -e "../build.zip" ] ;
    then
    	echo -e "${RED}'build.zip' exist! Removing it to avoid conflict.${RESET}"
        rm ../build.zip
    fi
    zip -ry ../build.zip . 
    echo -e "${GREEN}Packaging Complete :)${RESET}"
    #Doing secure copy to dev3 server
    cd ${OXHOME}
    echo -e "${YELLOW}Now Copying ${RED}build.zip${YELLOW} to $SERVER..${RESET}"
    ssh -i ${PEM} $SERVER ' mkdir -p oxzion3.0/deployment ;'
    scp -i ${PEM} build.zip $SERVER:oxzion3.0
    echo -e "${YELLOW}Copying ${RED}build.zip${YELLOW} to $SERVER completed successfully!${RESET}"
    echo -e "${GREEN}Build Completed on ${YELLOW}`date +%d-%m-%y` at `date +%H:%M:%S` Hours${RESET}"        
}
api()
{   
    cd ${OXHOME}
    echo -e "${YELLOW}Creating directory /build/api/v1...${RESET}"
    echo -e "${YELLOW}Setting up env files${RESET}"
    scp -i ${PEM} -r ${SERVER}:env/api/v1/config/autoload/local.php api/v1/config/autoload/
    echo -e "${GREEN}Copying Completed!${RESET}"
    #building API
    cd api/v1
    echo -e "${YELLOW}Building API....${RESET}"
    docker run -t -v ${PWD}:/var/www v1_zf composer install -n
    cd ${OXHOME}
    mkdir -p build/api/v1
    #copy contents of ap1v1 to build
    echo -e "${YELLOW}Copying Api/v1 to build folder....${RESET}"
    rsync -rl --delete api/v1 build/api/
    echo -e "${GREEN}Building API Completed!${RESET}"
}
camel()
{   
    cd ${OXHOME}
    echo -e "${YELLOW}Creating directory build/integrations/camel...${RESET}"
    mkdir -p build/integrations/camel
    #building camel
    cd ${OXHOME}/integrations/camel
    echo -e "${YELLOW}Building Camel${RESET}"
    echo -e "${YELLOW}Setting up env files${RESET}"
    scp -i ${PEM} -r ${SERVER}:env/integrations/camel/src/main/resources/* src/main/resources/
    #building camel
    docker run --network="host" -t -v ${PWD}:/workspace/app --entrypoint ./docker-build.sh camel
    echo -e "${GREEN}Building Camel Completed!${RESET}"
    echo -e "${YELLOW}Copying Camel to build folder...${RESET}"
    cp ./build/libs/app-0.0.1-SNAPSHOT.jar ../../build/integrations/camel/camel.jar
    cp -R ./init.d ../../build/integrations/camel
    echo -e "${GREEN}Copying Camel completed!${RESET}"
}
workflow()
{
    cd ${OXHOME}
    echo -e "${YELLOW}Creating directory build/integrations/workflow...${RESET}"
    mkdir -p build/integrations/workflow
    cd ${OXHOME}/integrations/workflow
    echo -e "${YELLOW}Building workflow....${RESET}"
    docker run -t -v ${PWD}:/camunda --entrypoint ./dockerbuild.sh workflow_build
    echo -e "${YELLOW}Building workflow completed....${RESET}"
    echo -e "${YELLOW}Copying workflow to build folder....${RESET}"
    cp ${OXHOME}/integrations/workflow/IdentityService/build/libs/identity_plugin-1.0.jar ${OXHOME}/build/integrations/workflow 
    cp ${OXHOME}/integrations/workflow/ProcessEngine/build/libs/processengine_plugin-1.0.jar ${OXHOME}/build/integrations/workflow 
    cp ${OXHOME}/integrations/workflow/bpm-platform.xml ${OXHOME}/build/integrations/workflow 
    echo -e "${GREEN}Copying workflow Completed!${RESET}"
}
#Update this specific for hub.
view()
{   
    cd ${OXHOME}
    echo -e "${YELLOW}Creating directory /build/view...${RESET}"
    cd view
    echo -e "${YELLOW}Build UI/view${RESET}"
    echo -e "${YELLOW}Setting up env files${RESET}"
    scp -i ${PEM} -r ${SERVER}:env/view/* ./
    docker run -t -v ${PWD}:/app view ./hubentrypoint.sh
    echo -e "${GREEN}Building UI/view Completed!${RESET}"
    cd ..
    #copy contents of view to build
    mkdir -p build/view
    echo -e "${YELLOW}Copying View to build folder. Please wait this may take sometime....${RESET}"
    rsync -rl --exclude=node_modules ./view ./build/
    mkdir -p ./build/view/bos/node_modules
    rsync -rl --delete ./view/bos/node_modules/ ./build/view/bos/node_modules/
    rsync -rl --delete ./view/gui/node_modules/ ./build/view/gui/node_modules/
    rsync -rl --delete ./view/node_modules/ ./build/view/node_modules/
    echo -e "${GREEN}Copying View Completed!${RESET}"
    #building UI/view folder
    
}
diveinsurance()
{
    cd ${OXHOME}
    echo -e "${YELLOW}Creating directory /build/clients...${RESET}"
    mkdir -p build/clients
    echo -e "${YELLOW}Copying clients DiveInsurance to build folder.${RESET}"
    rsync -rl clients/DiveInsurance/ ./build/clients/DiveInsurance/
    echo -e "${YELLOW}Copying clients DiveInsurance Completed.${RESET}"

}
task()
{
    cd ${OXHOME}
    echo -e "${YELLOW}Creating directory /build/clients...${RESET}"
    mkdir -p build/clients
    echo -e "${YELLOW}Copying clients Task to build folder.${RESET}"
    rsync -rl clients/Task/ ./build/clients/Task/
    echo -e "${YELLOW}Copying clients Task Completed.${RESET}"

}
bridgemed()
{
    cd ${OXHOME}
    echo -e "${YELLOW}Creating directory /build/clients...${RESET}"
    mkdir -p build/clients
    echo -e "${YELLOW}Copying clients BridgeMed to build folder.${RESET}"
    rsync -rl clients/BridgeMed/ ./build/clients/BridgeMed/
    echo -e "${YELLOW}Copying clients BridgeMed Completed.${RESET}"

}
integrations()
{
    camel
    workflow    
}
all()
{   
   integrations
   api
   view 
   diveinsurance
   task
   bridgemed
}

#looping through case from arguments passed
for i in $@
do
    case $i in
        api)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir
                api
                package
                break ;;
        view)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir
                view
                package
                break ;;
        diveinsurance)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir
                diveinsurance
                package
                break;;
        task)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir
                task
                package
                break;;
        bridgemed)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir
                bridgemed
                package
                break;;
        camel)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"
                check_dir
                camel
                package
                break ;;
        workflow)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir
                workflow
                package
                break ;;
        integrations)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir
                integrations
                package
                break ;;
        all)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"                
                check_dir                
                all
                package
                break ;;
        --help | -h)
                echo -e "${BLINK}${CYAN}███████╗ ██████╗ ██╗  ██╗    ██████╗ ██╗   ██╗██╗██╗     ██████╗ 
██╔════╝██╔═══██╗╚██╗██╔╝    ██╔══██╗██║   ██║██║██║     ██╔══██╗
█████╗  ██║   ██║ ╚███╔╝     ██████╔╝██║   ██║██║██║     ██║  ██║
██╔══╝  ██║   ██║ ██╔██╗     ██╔══██╗██║   ██║██║██║     ██║  ██║
███████╗╚██████╔╝██╔╝ ██╗    ██████╔╝╚██████╔╝██║███████╗██████╔╝
╚══════╝ ╚═════╝ ╚═╝  ╚═╝    ╚═════╝  ╚═════╝ ╚═╝╚══════╝╚═════╝ 
                                                                 ${RESET}"
                echo -e "This script is made to package oxzion3.0 to production build." 
                echo -e "This script takes 3 arguments to build oxzion-3.0.\nFirst the ${YELLOW}Build Option${RESET} Second the ${YELLOW}Server hostname${RESET} and third the${YELLOW}IdentityFile Path$RESET"
                echo -e "For example type \n$ ${GREEN}build.sh calendar$YELLOW(build option) ${GREEN}abc@xyz.com$YELLOW(server name)${GREEN} ~/.ssh/abc.pem${YELLOW}(identity file path)${RESET}"
                echo -e "For argument list type ${GREEN}'$0 list'${MAGENTA} as arguments${RESET}."
                break ;;
        --list | -l)
                buildhelp
                break ;;
        setup)  
                while true; do
                    echo -e "${RED}Warning! Only use for Fresh Setup, might break the server $SERVER!${RESET}"
                    read yn
                    case $yn in
                        [Yy]* ) scp -i ${PEM} deployment/freshsetup.sh $SERVER:oxzion3.0/deployment
                                ssh -i ${PEM} $SERVER 'sudo bash oxzion3.0/deployment/freshsetup.sh ;'
                                break;;
                        [Nn]* ) echo "Ok bye! ;)"
                                exit;;
                        * ) echo "Please type 'Yes' or 'No'.";;
                    esac
                done
                break ;;
        package)
                echo -e "Starting script ${INVERT}$0${RESET}...with ${MAGENTA}$@${RESET} as parameters"
                package
                break ;;
        deploy)
                ssh -i ${PEM} $SERVER ' mkdir -p oxzion3.0/deployment ;'
                scp -i ${PEM} deployment/deploy.sh $SERVER:oxzion3.0/deployment
                ssh -i ${PEM} $SERVER 'cd oxzion3.0/deployment ; sudo bash deploy.sh ;'
                break ;;
        clean)
                while true; do
                    echo -e "${RED}Warning! Are you sure you want to clean the server $SERVER?${RESET}"
                    read yn
                    case $yn in
                        [Yy]* ) echo -e "${YELLOW}Started Cleaning server $SERVER${RESET}"
                                ssh -i ${PEM} $SERVER ' rm -Rf oxzion3.0 ;'
                                ssh -i ${PEM} $SERVER ' mkdir -p oxzion3.0/deployment ;'
                                echo -e "${GREEN}Cleaning server Completed!${RESET}"
                                break;;
                        [Nn]* ) echo "Ok bye! ;)"
                                exit;;
                        * ) echo "Please type 'Yes' or 'No'.";;
                    esac
                done
                break ;;
                
        *)
                echo -e "${RED}Error : Wrong build option ${YELLOW}'$i'${RESET}"
                echo -e "Type '$0 --help' or '$0 -h' for more information."
                break ;;
    esac
done
finish_time="$(date +%s)"
min="$(( $((finish_time - start_time)) /60 ))"
sec="$(( $((finish_time - start_time)) %60 ))"
echo "Time elapsed $min mins and $sec secs."