#!/bin/bash



# Checking Permissions
Permission=$(id -u)
if [ $Permission -ne 0 ] 
then
	echo -e "\033[31m Root Privilege Required... \e[0m"
	echo -e "\033[31m Uses:  sudo $0 \e[0m"
	exit 100 
fi


# Capture Errors
OwnError()
{
	echo -e "[ `date` ] \033[31m $@ \e[0m" | tee -ai $INSTALLLOG
	exit 101
}


# Make Variables Available For Later Use
LOGDIR=/var/log/easyengine
INSTALLLOG=/var/log/easyengine/install.log



# Pre Checks To Avoid Later Screw Ups
# Checking Logs Directory
if [ ! -d $LOGDIR ]
then
	echo -e "\033[34m Creating easyengine log directory...  \e[0m" 
	mkdir -p $LOGDIR || OwnError "Unable to create log directory $LOGDIR"
fi

# Checking Tee
if [ ! -x  /usr/bin/tee ]
then
	echo -e "\033[31m Tee command not found !! \e[0m"
	echo -e "\033[34m Installing tee  \e[0m"
	sudo apt-get -y install coreutils || OwnError "Unable to install tee"
fi

# Checking Wget
if [ ! -x  /usr/bin/wget ]
then
	echo -e "\033[31m Wget command not found !! \e[0m"
	echo -e "\033[34m Installing wget  \e[0m"
	sudo apt-get -y install wget || OwnError "Unable to install wget"
fi

# Checking Tar
if [ ! -x  /bin/tar ]
then
	echo -e "\033[31m Tar command not found !! \e[0m"
	echo -e "\033[34m Installing tar  \e[0m"
	sudo apt-get -y install tar || OwnError "Unable to install tar"
fi

# Checking Name Servers
if [[ -z $(cat /etc/resolv.conf | grep -v ^#) ]]
then
	echo -e "\033[31m No nameservers detected !! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[31m Please configure /etc/resolv.conf \e[0m" | tee -ai $INSTALLLOG
	exit 100
fi

# Checking Git
if [ ! -x  /usr/bin/git ]
then
	echo -e "\033[31m Git command not found !! \e[0m"
	echo -e "\033[34m Installing git  \e[0m"
	sudo apt-get -y install git-core || OwnError "Unable to install git"
fi

# Pre Checks End


# Check The Easy Engine Is Available
EXIST=$(basename `pwd`)
if [ "$EXIST" != "easyengine" ]
then
	echo -e "\033[34m Cloning Easy Engine, please wait...  \e[0m" | tee -ai $INSTALLLOG
	cd /tmp

	# Remove Older Easy Engine If Found
	rm -rf /tmp/easyengine

	# Git Clone
	git clone git://github.com/rtCamp/easyengine.git || OwnError "Unable to clone easy engine"
	cd easyengine
fi

# Create Directory /usr/share/easyengine
if [ ! -d /usr/share/easyengine ]
then
	mkdir -p /usr/share/easyengine \
	|| OwnError "Unable to create dir /usr/share/easyengine"
fi

# Install Easy Engine
echo -e "\033[34m Installing Easy Engine, please wait...  \e[0m" | tee -ai $INSTALLLOG
cp -av conf/* /usr/share/easyengine
cp -av setup/engine /usr/local/sbin/

# Create Symbolic Link If Not Exist
if [ ! -L /usr/local/sbin/ee ]
then
	ln -s /usr/local/sbin/engine /usr/local/sbin/ee
fi







echo
echo "Easy Engine Installed"

