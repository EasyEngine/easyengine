#!/bin/bash



# Update apt cache
echo -e "\033[34mUpdating apt cache, please wait...\e[0m"
apt-get update &>> /dev/null

# Checking lsb_release
if [ ! -x  /usr/bin/lsb_release ]; then
	echo -e "\033[31mThe lsb_release command not found\e[0m"
	echo -e "\033[34mInstalling lsb-release, please wait...\e[0m"
	apt-get -y install lsb-release &>> /dev/null
fi

# Define variables for later use
LOG_DIR=/var/log/easyengine
INSTALL_LOG=/var/log/easyengine/install.log
LINUX_DISTRO=$(lsb_release -i |awk '{print $3}')

# Checking linux distro
if [ "$LINUX_DISTRO" != "Ubuntu" ] && [ "$LINUX_DISTRO" != "Debian" ]; then
	echo -e "\033[31mEasyEngine (ee) is made for Ubuntu and Debian only as of now\e[0m"
	echo -e "\033[31mYou are free to fork EasyEngine (ee): https://github.com/rtCamp/easyengine/fork\e[0m"
	exit 100
fi

# Checking permissions
if [[ $EUID -ne 0 ]]; then
	echo -e "\033[31mSudo privilege required...\e[0m"
	echo -e "\033[31mUses:\e[0m\033[34m curl -sL rt.cx/ee | sudo bash\e[0m"
	exit 101
fi

# Capture errors
function EE_ERROR()
{
	echo -e "[ `date` ] \033[31m$@\e[0m" | tee -ai $INSTALL_LOG
	exit 102
}

# Pre checks to avoid later screw ups
# Checking EasyEngine (ee) log directory
if [ ! -d $LOG_DIR ]; then
	echo -e "\033[34mCreating EasyEngine (ee) log directory, please wait...\e[0m"
	mkdir -p $LOG_DIR || EE_ERROR "Unable to create log directory $LOG_DIR"
fi

if [ ! -x  /usr/bin/tee ] || [ ! -x  /bin/ed ] || [ ! -x  /usr/bin/bc ] || [ ! -x  /usr/bin/wget ] || [ ! -x  /usr/bin/curl ] || [ ! -x  /bin/tar ] || [ ! -x  /usr/bin/git ]; then
	echo -e "\033[31mInstalling required packages\e[0m" | tee -ai $INSTALL_LOG
	apt-get -y install coreutils ed bc wget curl tar git-core || EE_ERROR "Unable to install required packages"
fi

# Checking name servers
if [[ -z $(cat /etc/resolv.conf 2> /dev/null | awk '/^nameserver/ { print $2 }') ]]; then
	echo -e "\033[31mUnable to detect name servers\e[0m" && EE_ERROR "Unable to detect name servers"
	echo -e "\033[31mPlease configure /etc/resolv.conf\e[0m" && EE_ERROR "Please configure /etc/resolv.conf"
fi
# Pre checks end

# Decide EasyEngine branch
if [ -z "$EE_BRANCH" ]; then
	EE_BRANCH=stable
else
	# Cross check EasyEngine (ee) branch name
	git ls-remote --heads https://github.com/rtCamp/easyengine | grep $EE_BRANCH &>> $INSTALL_LOG
	if [ $? -ne 0 ]; then
		echo -e "\033[31mThe $EE_BRANCH branch does not exist, please provide the correct branch name\e[0m" \
		&& EE_ERROR "The $EE_BRANCH branch does not exist, please provide the correct branch name"
	fi
fi

# Remove old version of EasyEngine (ee) 
rm -rf /tmp/easyengine &>> /dev/null

# Let's clone EasyEngine (ee)
echo -e "\033[34mCloning EasyEngine (ee) $EE_BRANCH branch, please wait...\e[0m" | tee -ai $INSTALL_LOG
git clone -b $EE_BRANCH git://github.com/rtCamp/easyengine.git /tmp/easyengine &>> $INSTALL_LOG || EE_ERROR "Unable to clone EasyEngine (ee) $EE_BRANCH branch"


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
echo -e "\033[34mInstalling EasyEngine (ee), please wait...\e[0m" | tee -ai $INSTALL_LOG

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
GIT_EMAIL=$(git config user.email)
GIT_USERNAME=$(git config user.name)

if [ -z "$GIT_USERNAME" ] || [ -z "$GIT_EMAIL" ]; then
	echo
	echo -e "\033[34mEasyEngine (ee) required your name & email address to track changes you made under the git version control\e[0m" | tee -ai $INSTALL_LOG
	echo -e "\033[34mEasyEngine (ee) will be able to send you daily reports & alerts in upcoming version\e[0m" | tee -ai $INSTALL_LOG
	echo -e "\033[34mEasyEngine (ee) will NEVER send your information across\e[0m" | tee -ai $INSTALL_LOG
fi

# 
if [ -z "$GIT_USERNAME" ]; then
	read -p "Enter your name [$(whoami)]: " GIT_USERNAME
	# If enter is pressed
	if [[ $GIT_USERNAME = "" ]]
	then
		GIT_USERNAME=$(whoami)
	fi
	git config --global user.name "$GIT_USERNAME" &>> $INSTALL_LOG	
fi

if [ -z "$GIT_EMAIL" ];then
	read -p "Enter your email address [$(whoami)@$(hostname -f)]: " GIT_EMAIL
	# If enter is pressed
	if [[ $GIT_EMAIL = "" ]]
	then
		GIT_EMAIL=$(whoami)@$(hostname -f)
	fi
	git config --global user.email $GIT_EMAIL &>> $INSTALL_LOG
fi

# Enable EasyEngine (ee) auto completion
echo
echo -e "\033[34mTo enable EasyEngine (ee) auto completion, run the following command\e[0m" | tee -ai $INSTALL_LOG
echo -e "\033[37msource /etc/bash_completion.d/ee\e[0m" | tee -ai $INSTALL_LOG
echo

# Display success message
echo -e "\033[34mEasyEngine (ee) installed successfully\e[0m" | tee -ai $INSTALL_LOG
echo -e "\033[34mEasyEngine (ee) help: http://rtcamp.com/easyengine/docs/\e[0m" | tee -ai $INSTALL_LOG
echo
