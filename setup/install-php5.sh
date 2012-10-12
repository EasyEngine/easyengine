#!/bin/bash



# Capture Errors
OwnError()
{
	#echo $@ >&2
	clear
	echo -e "[ $0 ][ `date` ] \033[31m $@ \e[0m" | tee -ai /var/log/easyengine/error.log
	exit 100
}


# Install Python Software Properties
sudo apt-get -y install python-software-properties || OwnError "Unable To Install Python Software Properties :("

# Add Nginx Launchpad Repository
sudo add-apt-repository ppa:ondrej/php5 || OwnError "Unable To Add PHP5 Launchpad Repository :("

# Update The APT Cache
sudo apt-get update || OwnError "Unable To Update APT Cache :("

# Install PHP5
sudo apt-get -y install php5-common php5-mysql php5-xmlrpc php5-cgi php5-curl php5-gd php5-cli php5-fpm php-apc php-pear php5-dev php5-imap php5-mcrypt || OwnError "Unable To Install PHP5 :("

# Check PHP5 Version
php -v || OwnError "Unable To Detect PHP Version :("

