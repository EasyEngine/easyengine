#!/bin/bash



# Capture Errors
OwnError()
{
	echo -e "[ $0 ][ `date` ] \033[31m $@ \e[0m" | tee -ai $ERRORLOG
	exit 101
}


# Install Python Software Properties
sudo apt-get -y install python-software-properties || OwnError "Unable To Install Python Software Properties"

# Add Nginx Launchpad Repository
sudo add-apt-repository ppa:nginx/stable || OwnError "Unable To Add Nginx Launchpad Repository"

# Update The APT Cache
sudo apt-get update || OwnError "Unable To Update APT Cache"

# Install Nginx
sudo apt-get -y install nginx || OwnError "Unable To Install Nginx"

# Check Nginx Version
nginx -v || OwnError "Unable To Detect Nginx Version"
