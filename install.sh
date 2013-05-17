#!/bin/bash



# Checking Permissions
Permission=$(id -u)
if [ $Permission -ne 0 ] 
then
	echo -e "\033[31m Sudo Privilege Required... \e[0m"
	echo -e "\033[31m Uses:\e[0m \033[34m curl -sL rt.cx/ee | sudo bash \e[0m"
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
	echo -e "\033[34m Creating Easy Engine Log Directory...  \e[0m" 
	mkdir -p $LOGDIR || OwnError "Unable To Create Log Directory $LOGDIR"
fi

# Checking Tee
if [ ! -x  /usr/bin/tee ]
then
	echo -e "\033[31m Tee Command Not Found !! \e[0m"
	echo -e "\033[34m Installing Tee  \e[0m"
	sudo apt-get -y install coreutils || OwnError "Unable to install tee"
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
	echo -e "\033[31m No Name Servers Detected !! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[31m Please Configure /etc/resolv.conf \e[0m" | tee -ai $INSTALLLOG
	exit 102
fi

# Checking Git
if [ ! -x  /usr/bin/git ]
then
	echo -e "\033[31m Git Command Not Found !! \e[0m"
	echo -e "\033[34m Installing Git, Please Wait...  \e[0m"
	sudo apt-get -y install git-core || OwnError "Unable To Install Git"
fi

# Checking WP-CLI
if [ ! -d /root/wp-cli ]
then
	echo -e "\033[31m WP Command Not Found !! \e[0m"
	echo -e "\033[34m Installing WP-CLI, Please Wait...  \e[0m"
	git clone git://github.com/wp-cli/wp-cli.git /root/wp-cli
	sudo /root/wp-cli/utils/dev-build || OwnError "Unable To Build WP-CLI"
fi

# Pre Checks End


# Check The Easy Engine Is Available
EXIST=$(basename `pwd`)
if [ "$EXIST" != "easyengine" ]
then
	echo -e "\033[34m Cloning Easy Engine, Please Wait...  \e[0m" | tee -ai $INSTALLLOG
	cd /tmp

	# Remove Older Easy Engine If Found
	rm -rf /tmp/easyengine &>> /dev/null

	# Git Clone
	git clone git://github.com/rtCamp/easyengine.git || OwnError "Unable To Clone Easy Engine"
	cd easyengine
fi

# Create Directory /usr/share/easyengine
if [ ! -d /usr/share/easyengine ]
then
	mkdir -p /usr/share/easyengine \
	|| OwnError "Unable To Create Dir /usr/share/easyengine"
fi

# Install Easy Engine
echo -e "\033[34m Installing Easy Engine, Please Wait...  \e[0m" | tee -ai $INSTALLLOG
cp -a conf/* /usr/share/easyengine &>> /dev/null || OwnError "Unable To Copy Configuration Files "
cp -a setup/engine /usr/local/sbin/ &>> /dev/null || OwnError "Unable To Copy Engine Command"
chmod 750 /usr/local/sbin/engine || OwnError "Unable To Change Engine Coommand Permission"

# Create Symbolic Link If Not Exist
if [ ! -L /usr/local/sbin/ee ]
then
	ln -s /usr/local/sbin/engine /usr/local/sbin/ee
fi





echo
echo -e "\033[34m Easy Engine Installed Successfully \e[0m" | tee -ai $INSTALLLOG
echo
