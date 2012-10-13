#!/bin/bash



# Capture Errors
OwnError()
{
        echo -e "[ $0 ][ `date` ] \033[31m $@ \e[0m" | tee -ai $ERRORLOG
        exit 101
}




# Checking Permissions
Permission=$(id -u)
if [ $Permission -ne 0 ] 
then
        echo -e "\033[31m Root Privilege Required ... \e[0m"
        echo -e "\033[31m Uses:  sudo $0 \e[0m"
        exit 100
fi

# Checking Logs Directory
if [ ! -d $LOGDIR ]
then
	mkdir -p $LOGDIR || OwnError "Unable To Create Log Directory $LOGDIR"
else
	echo -e "\033[34m Easy Engine Log Directory Found  \e[0m"
fi




# Checking Tee
if [ ! -x  /usr/bin/tee ]
then
        echo -e "\033[31m Tee Command Not Found !! \e[0m"
        echo -e "\033[34m Installing Tee  \e[0m"
        sudo apt-get -y install coreutils || OwnError "Unable To Install Tee"
fi

# Checking Wget
if [ ! -x  /usr/bin/wget ]
then
	echo -e "\033[31m Wget Command Not Found !! \e[0m"
	echo -e "\033[34m Installing Wget  \e[0m"
	sudo apt-get -y install wget || OwnError "Unable To Install Wget"
fi

# Checking Tar
if [ ! -x  /bin/tar ]
then
	echo -e "\033[31m Tar Command Not Found !! \e[0m"
	echo -e "\033[34m Installing Tar  \e[0m"
	sudo apt-get -y install tar || OwnError "Unable To Install Tar"
fi


# Checking Name Servers
if [[ -z $(cat /etc/resolv.conf | grep -v ^#) ]]
then
	echo -e "\033[31m No Nameservers Detected !! \e[0m" | tee -ai $ERRORLOG
	echo -e "\033[31m Please configure /etc/resolv.conf \e[0m" | tee -ai $ERRORLOG
	exit 100
fi
