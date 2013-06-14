#!/bin/bash



# Checking Permissions
Permission=$(id -u)
if [ $Permission -ne 0 ] 
then
	echo -e "\033[31m Sudo Privilege Required... \e[0m"
	echo -e "\033[31m Uses:\e[0m\033[34m curl -sL rt.cx/ee | sudo bash \e[0m"
	exit 100 
fi


# Make Variables Available For Later Use
LOGDIR=/var/log/easyengine
INSTALLLOG=/var/log/easyengine/install.log


# Capture Errors
OwnError()
{
	echo -e "[ `date` ] \033[31m $@ \e[0m" | tee -ai $INSTALLLOG
	exit 101
}


# Pre Checks To Avoid Later Screw Ups
# Checking Logs Directory

if [ ! -d $LOGDIR ]
then
	echo -e "\033[34m Creating Easy Engine Log Directory, Please Wait...  \e[0m"
	mkdir -p $LOGDIR || OwnError "Unable To Create Log Directory $LOGDIR"
fi

echo &>> $INSTALLLOG
echo &>> $INSTALLLOG
echo -e "\033[34m EasyEngine Installation Started `date +"%d-%b-%Y %H:%M:%S"` \e[0m" | tee -ai $INSTALLLOG

# Checking Tee
if [ ! -x  /usr/bin/tee ]
then
	echo -e "\033[31m Tee Command Not Found ! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34m Installing Tee  \e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install coreutils || OwnError "Unable to install tee"
fi

# Checking Wget
if [ ! -x  /usr/bin/wget ]
then
	echo -e "\033[31m Wget Command Not Found ! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34m Installing Wget  \e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install wget || OwnError "Unable To Install Wget"
fi

# Checking Curl
if [ ! -x  /usr/bin/curl ]
then
	echo -e "\033[31m Curl Command Not Found ! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34m Installing Curl  \e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install curl || OwnError "Unable To Install Curl"
fi

# Checking Tar
if [ ! -x  /bin/tar ]
then
	echo -e "\033[31m Tar Command Not Found ! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34m Installing Tar  \e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install tar || OwnError "Unable To Install Tar"
fi

# Checking Name Servers
if [[ -z $(cat /etc/resolv.conf | grep -v ^#) ]]
then
	echo -e "\033[31m No Name Servers Detected ! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[31m Please Configure /etc/resolv.conf \e[0m" | tee -ai $INSTALLLOG
	exit 102
fi

# Checking Git
if [ ! -x  /usr/bin/git ]
then
	echo -e "\033[31m Git Command Not Found ! \e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34m Installing Git, Please Wait...  \e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install git-core || OwnError "Unable To Install Git"
fi

# Pre Checks End


# Check The Easy Engine Is Available
EXIST=$(basename `pwd`)
if [ "$EXIST" != "easyengine" ]
then
	echo -e "\033[34m Cloning Easy Engine, Please Wait...  \e[0m" | tee -ai $INSTALLLOG
	
	# Remove Older Easy Engine If Found
	rm -rf /tmp/easyengine &>> /dev/null

	# Clone Easy Engine Repository
	git clone git://github.com/rtCamp/easyengine.git /tmp/easyengine || OwnError "Unable To Clone Easy Engine"
fi

# Create Directory /etc/easyengine
if [ ! -d /etc/easyengine ]
then
	# Create A Directory For EE Configurations
	mkdir -p /etc/easyengine || OwnError "Unable To Create Dir /etc/easyengine"
fi

# Create Directory /usr/share/easyengine
if [ ! -d /usr/share/easyengine ]
then
	# Create A Directory For EE Nginx Configurations
	mkdir -p /usr/share/easyengine \
	|| OwnError "Unable To Create Dir /usr/share/easyengine"
fi

# Install Easy Engine
echo -e "\033[34m Installing Easy Engine, Please Wait...  \e[0m" | tee -ai $INSTALLLOG

# EE /etc Files
cp -av /tmp/easyengine/etc/ee /etc/bash_completion.d/ &>> /dev/null || OwnError "Unable To Copy EE Auto Complete File"
cp -av /tmp/easyengine/etc/ee.conf /etc/easyengine/ &>> /dev/null || OwnError "Unable To Copy ee.conf File"

# EE /usr/share/easyengine Files
cp -av /tmp/easyengine/etc/nginx/ /usr/share/easyengine/nginx/ &>> /dev/null || OwnError "Unable To Copy Configuration Files "
cp -av /tmp/easyengine/usr/share/* /usr/share/easyengine/ &>> /dev/null || OwnError "Unable To Copy Configuration Files "

# EE Command
cp -av /tmp/easyengine/usr/local/sbin/easyengine /usr/local/sbin/ &>> /dev/null || OwnError "Unable To Copy EasyEngine Command"

# Change Permission For EE
chmod 750 /usr/local/sbin/easyengine || OwnError "Unable To Change EasyEngine Command Permission"

# Create Symbolic Link If Not Exist
if [ ! -L /usr/local/sbin/ee ]
then
	ln -s /usr/local/sbin/easyengine /usr/local/sbin/ee
else
	rm /usr/local/sbin/ee
	ln -s /usr/local/sbin/easyengine /usr/local/sbin/ee
fi


# Copy Defaults EE Options 
echo
echo -e "\033[34m Easy Engine Installed Successfully \e[0m" | tee -ai $INSTALLLOG
echo