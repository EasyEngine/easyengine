#!/bin/bash



# Define echo function for each color
function ECHO_RED()
{
	echo $(tput setaf 1)$@$(tput sgr0)
}

function ECHO_BLUE()
{
	echo $(tput setaf 4)$@$(tput sgr0)
}

function ECHO_WHITE()
{
	echo $(tput setaf 7)$@$(tput sgr0)
}

# Check the specified package is instlled or not
function PACKAGE_CHECK()
{
	for i in $@;do
		dpkg --get-selections | grep -v deinstall | grep $i &>> INSTALL_LOG
		# Generate a list of not installed package
		if [ $? -ne 0 ]; then
			PACKAGE_NAME="$PACKAGE_NAME $i"
		fi
	done
}

# Update apt cache
ECHO_BLUE "Updating apt cache, please wait..."
apt-get update &>> /dev/null

# Checking lsb_release
if [ ! -x  /usr/bin/lsb_release ]; then
	ECHO_BLUE "Installing lsb-release, please wait..."
	apt-get -y install lsb-release &>> /dev/null
fi

# Define variables for later use
LOG_DIR=/var/log/easyengine
INSTALL_LOG=/var/log/easyengine/install.log
LINUX_DISTRO=$(lsb_release -i |awk '{print $3}')

# Checking linux distro
if [ "$LINUX_DISTRO" != "Ubuntu" ] && [ "$LINUX_DISTRO" != "Debian" ]; then
	ECHO_RED "EasyEngine (ee) is made for Ubuntu and Debian only as of now"
	ECHO_RED "You are free to fork EasyEngine (ee): https://github.com/rtCamp/easyengine/fork"
	exit 100
fi

# Checking permissions
if [[ $EUID -ne 0 ]]; then
	ECHO_RED "Sudo privilege required..."
	ECHO_RED "Uses: curl -sL rt.cx/ee | sudo bash"
	exit 101
fi

# Capture errors
function EE_ERROR()
{
	echo "[ `date` ] $(tput setaf 1)$@$(tput sgr0)" | tee -ai $INSTALL_LOG
	exit 102
}

# Pre checks to avoid later screw ups
# Checking EasyEngine (ee) log directory
if [ ! -d $LOG_DIR ]; then
	ECHO_BLUE "Creating EasyEngine (ee) log directory, please wait..."
	mkdir -p $LOG_DIR || EE_ERROR "Unable to create log directory $LOG_DIR"
fi

# Install required packages
if [ "$LINUX_DISTRO" == "Ubuntu" ]; then
	PACKAGE_CHECK graphviz python-software-properties software-properties-common
elif [ "$LINUX_DISTRO" == "Debian" ]; then
	PACKAGE_CHECK graphviz python-software-properties
fi

if [ ! -x  /usr/bin/tee ] || [ ! -x  /bin/ed ] || [ ! -x  /usr/bin/bc ] || [ ! -x  /usr/bin/wget ] || [ ! -x  /usr/bin/curl ] || [ ! -x  /bin/tar ] || [ ! -x  /usr/bin/git ] || [ -n $PACKAGE_NAME ]; then
	ECHO_BLUE "Installing required packages" | tee -ai $INSTALL_LOG
	apt-get -y install coreutils ed bc wget curl tar git-core $PACKAGE_NAME || EE_ERROR "Unable to install required packages"
fi

# Checking name servers
if [[ -z $(cat /etc/resolv.conf 2> /dev/null | awk '/^nameserver/ { print $2 }') ]]; then
	ECHO_RED "Unable to detect name servers" && EE_ERROR "Unable to detect name servers"
	ECHO_RED "Please configure /etc/resolv.conf" && EE_ERROR "Please configure /etc/resolv.conf"
fi
# Pre checks end

# Decide EasyEngine branch
if [ -z "$BRANCH_NAME" ]; then
	BRANCH_NAME=stable
else
	# Cross check EasyEngine (ee) branch name
	git ls-remote --heads https://github.com/rtCamp/easyengine | grep $BRANCH_NAME &>> $INSTALL_LOG
	if [ $? -ne 0 ]; then
		EE_ERROR "The $BRANCH_NAME branch does not exist, please provide the correct branch name"
	fi
fi

# Remove old version of EasyEngine (ee) 
rm -rf /tmp/easyengine &>> /dev/null

# Let's clone EasyEngine (ee)
ECHO_BLUE "Cloning EasyEngine (ee) $BRANCH_NAME branch, please wait..." | tee -ai $INSTALL_LOG
git clone -b $BRANCH_NAME git://github.com/rtCamp/easyengine.git /tmp/easyengine &>> $INSTALL_LOG || EE_ERROR "Unable to clone EasyEngine (ee) $BRANCH_NAME branch"


# Setup EasyEngine (ee)
# Create EasyEngine (ee) configuration directory
if [ ! -d /etc/easyengine ]; then
	mkdir -p /etc/easyengine \
	|| EE_ERROR "Unable to create /etc/easyengine directory"
fi

# Nginx sample config directory
if [ ! -d /usr/share/easyengine/nginx ]
then
	mkdir -p /usr/share/easyengine/nginx \
	|| EE_ERROR "Unable to create /usr/share/easyengine/nginx directory"
fi

# Install EasyEngine (ee)
ECHO_BLUE "Installing EasyEngine (ee), please wait..." | tee -ai $INSTALL_LOG

# EasyEngine (ee) auto completion file
cp -a /tmp/easyengine/etc/bash_completion.d/ee /etc/bash_completion.d/ &>> $INSTALL_LOG \
|| EE_ERROR "Unable to copy EasyEngine (ee) auto completion file"

# EasyEngine (ee) config file
cp -a /tmp/easyengine/etc/easyengine/ee.conf /etc/easyengine/ &>> $INSTALL_LOG \
|| EE_ERROR "Unable to copy EasyEngine (ee) config file"

# Nginx sample files
cp -a /tmp/easyengine/etc/nginx /tmp/easyengine/usr/share/easyengine/* /usr/share/easyengine/ &>> $INSTALL_LOG \
|| EE_ERROR "Unable to copy nginx sample files"

# EasyEngine (ee) command
cp -a /tmp/easyengine/usr/local/sbin/easyengine /usr/local/sbin/ &>> $INSTALL_LOG \
|| EE_ERROR "Unable to copy EasyEngine (ee) command"

# EasyEngine (ee) man page
cp -a /tmp/easyengine/man/ee.8 /usr/share/man/man8/ &>> $INSTALL_LOG \
|| EE_ERROR "Unable to copy EasyEngine (ee) man page"

# Change permission of EasyEngine (ee) command
chmod 750 /usr/local/sbin/easyengine || EE_ERROR "Unable to change permission of EasyEngine (ee) command"

# Create symbolic link
if [ ! -L /usr/local/sbin/ee ]; then
	ln -s /usr/local/sbin/easyengine /usr/local/sbin/ee
fi

# Git config settings
GIT_USER_NAME=$(git config user.name)
GIT_USER_EMAIL=$(git config user.email)

if [ -z "$GIT_USER_NAME" ] || [ -z "$GIT_USER_EMAIL" ]; then
	echo
	ECHO_BLUE "EasyEngine (ee) required your name & email address" | tee -ai $INSTALL_LOG
	ECHO_BLUE "to track changes you made under the git version control" | tee -ai $INSTALL_LOG
	ECHO_BLUE "EasyEngine (ee) will be able to send you daily reports & alerts in upcoming version" | tee -ai $INSTALL_LOG
	ECHO_BLUE "EasyEngine (ee) will NEVER send your information across" | tee -ai $INSTALL_LOG
fi

if [ -z "$GIT_USER_NAME" ]; then
	read -p "Enter your name [$(whoami)]: " GIT_USER_NAME
	# If enter is pressed
	if [[ $GIT_USER_NAME = "" ]]
	then
		GIT_USER_NAME=$(whoami)
	fi
	git config --global user.name "$GIT_USER_NAME" &>> $INSTALL_LOG	
fi

if [ -z "$GIT_USER_EMAIL" ];then
	read -p "Enter your email address [$(whoami)@$(hostname -f)]: " GIT_USER_EMAIL
	# If enter is pressed
	if [[ $GIT_USER_EMAIL = "" ]]
	then
		GIT_USER_EMAIL=$(whoami)@$(hostname -f)
	fi
	git config --global user.email $GIT_USER_EMAIL &>> $INSTALL_LOG
fi

# Enable EasyEngine (ee) auto completion
echo
ECHO_BLUE "To enable EasyEngine (ee) auto completion, run the following command" | tee -ai $INSTALL_LOG
ECHO_WHITE "source /etc/bash_completion.d/ee" | tee -ai $INSTALL_LOG
echo

# Display success message
ECHO_BLUE "EasyEngine (ee) installed successfully" | tee -ai $INSTALL_LOG
ECHO_BLUE "EasyEngine (ee) help: http://rtcamp.com/easyengine/docs/" | tee -ai $INSTALL_LOG
echo
