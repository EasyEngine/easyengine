#!/bin/bash



# Capture Errors
OwnError()
{
	echo -e "[ $0 ][ `date` ] \033[31m $@ \e[0m" | tee -ai $ERRORLOG
	exit 101
}


# Update The APT Cache
sudo apt-get update || OwnError "Unable To Update APT Cache"

# Install MySQL
sudo apt-get -y install mysql-server mysqltuner || OwnError "Unable To Install MySQL"

