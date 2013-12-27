#!/bin/bash



# Checking Linux Distro Is Ubuntu
if [ ! -f /etc/lsb-release ]
then
	echo -e "\033[31mEasyEngine (ee) Is Made For Ubuntu Only As Of Now\e[0m"
	echo -e "\033[31mYou Are Free To Fork EasyEngine (ee): https://github.com/rtCamp/easyengine/fork\e[0m"
	exit 100
fi



# Checking Permissions
Permission=$(id -u)
if [ $Permission -ne 0 ] 
then
	echo -e "\033[31mSudo Privilege Required...\e[0m"
	echo -e "\033[31mUses:\e[0m\033[34m curl -sL rt.cx/ee | sudo bash\e[0m"
	exit 100 
fi


# Make Variables Available For Later Use
LOGDIR=/var/log/easyengine
INSTALLLOG=/var/log/easyengine/install.log


# Capture Errors
OwnError()
{
	echo -e "[ `date` ] \033[31m$@\e[0m" | tee -ai $INSTALLLOG
	exit 101
}


# Pre Checks To Avoid Later Screw Ups
# Checking Logs Directory

if [ ! -d $LOGDIR ]
then
	echo -e "\033[34mCreating EasyEngine (ee) Log Directory, Please Wait...\e[0m"
	mkdir -p $LOGDIR || OwnError "Unable To Create Log Directory $LOGDIR"
fi

# Checking Tee
if [ ! -x  /usr/bin/tee ]
then
	echo -e "\033[31mTee Command Not Found\e[0m"
	echo -e "\033[34mInstalling Tee, Please Wait...\e[0m"
	sudo apt-get -y install coreutils &>> $INSTALLLOG || OwnError "Unable to install tee"
fi

echo &>> $INSTALLLOG
echo &>> $INSTALLLOG
echo -e "\033[34mEasyEngine (ee) Installation Started [$(date)]\e[0m" | tee -ai $INSTALLLOG


# Checking Ed
if [ ! -x  /bin/ed ]
then
	echo -e "\033[31mEd Command Not Found\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34mInstalling Ed, Please Wait...\e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install ed &>> $INSTALLLOG || OwnError "Unable to install ed"
fi

# Checking Wget
if [ ! -x  /usr/bin/wget ]
then
	echo -e "\033[31mWget Command Not Found\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34mInstalling Wget, Please Wait...\e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install wget &>> $INSTALLLOG || OwnError "Unable To Install Wget"
fi

# Checking Curl
if [ ! -x  /usr/bin/curl ]
then
	echo -e "\033[31mCurl Command Not Found\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34mInstalling Curl, Please Wait...\e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install curl &>> $INSTALLLOG || OwnError "Unable To Install Curl"
fi

# Checking Tar
if [ ! -x  /bin/tar ]
then
	echo -e "\033[31mTar Command Not Found\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34mInstalling Tar, Please Wait...\e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install tar &>> $INSTALLLOG || OwnError "Unable To Install Tar"
fi

# Checking Git
if [ ! -x  /usr/bin/git ]
then
	echo -e "\033[31mGit Command Not Found\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34mInstalling Git, Please Wait...\e[0m" | tee -ai $INSTALLLOG
	sudo apt-get -y install git-core &>> $INSTALLLOG || OwnError "Unable To Install Git"
fi

# Checking Name Servers
if [[ -z $(cat /etc/resolv.conf 2> /dev/null | awk '/^nameserver/ { print $2 }') ]]
then
	echo -e "\033[31mNo Name Servers Detected\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[31mPlease Configure /etc/resolv.conf\e[0m" | tee -ai $INSTALLLOG
	exit 102
fi

# Pre Checks End


# Check The EasyEngine (ee) Is Available
EXIST=$(basename `pwd`)
if [ "$EXIST" != "easyengine" ]
then
	echo -e "\033[34mCloning EasyEngine (ee), Please Wait...\e[0m" | tee -ai $INSTALLLOG
	
	# Remove Older EasyEngine (ee) If Found
	rm -rf /tmp/easyengine &>> /dev/null

	# Clone EasyEngine (ee) Stable Repository
	git clone -b stable git://github.com/rtCamp/easyengine.git /tmp/easyengine &>> $INSTALLLOG || OwnError "Unable To Clone Easy Engine"
fi

# Create Directory /etc/easyengine
if [ ! -d /etc/easyengine ]
then
	# Create A Directory For EE Configurations
	mkdir -p /etc/easyengine || OwnError "Unable To Create Dir /etc/easyengine"
fi

# Create Directory /usr/share/easyengine
if [ ! -d /usr/share/easyengine/nginx ]
then
	# Create A Directory For EE Nginx Configurations
	mkdir -p /usr/share/easyengine/nginx || OwnError "Unable To Create Dir /usr/share/easyengine/nginx"
fi

# Install EasyEngine (ee)
echo -e "\033[34mInstalling EasyEngine (ee), Please Wait...\e[0m" | tee -ai $INSTALLLOG

# EE /etc Files
cp -a /tmp/easyengine/etc/bash_completion.d/ee /etc/bash_completion.d/ &>> $INSTALLLOG || OwnError "Unable To Copy EE Auto Complete File"
cp -a /tmp/easyengine/etc/easyengine/ee.conf /etc/easyengine/ &>> $INSTALLLOG || OwnError "Unable To Copy ee.conf File"

# EE /usr/share/easyengine Files
cp -a /tmp/easyengine/etc/nginx/* /usr/share/easyengine/nginx/ &>> $INSTALLLOG || OwnError "Unable To Copy Configuration Files "
cp -a /tmp/easyengine/usr/share/easyengine/* /usr/share/easyengine/ &>> $INSTALLLOG || OwnError "Unable To Copy Configuration Files "

# EE Command
cp -a /tmp/easyengine/usr/local/sbin/easyengine /usr/local/sbin/ &>> $INSTALLLOG || OwnError "Unable To Copy EasyEngine Command"

# EE Man Pages
cp -a /tmp/easyengine/man/ee.8 /usr/share/man/man8/ &>> $INSTALLLOG || OwnError "Unable To Copy EasyEngine Man Pages"

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

# Adjust FastCGI Cache Size 20% Of /var/run
VARRUNSIZE=$(df --block-size=M /var/run | awk '{print $4}' | tail -n1 |cut -d'M' -f1)
FCSIZE=$(expr $VARRUNSIZE \* 25 / 100)

# Change Size
sed -i "s/500m/$FCSIZE\m/" /usr/share/easyengine/nginx/conf.d/fastcgi.conf || OwnError "Unable To Change Fastcgi Cache Size"

# Git Config Settings
EEGITNAME=$(git config user.name)
EEGITEMAIL=$(git config user.email)

if [ -z "$EEGITNAME" ] || [ -z "$EEGITEMAIL" ]
then
	echo
	echo -e "\033[34mEasyEngine (ee) Required Your Name & Email Address To Track Changes You Made Under The Git\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34mEasyEngine (ee) Will Be Able To Send You Daily Reports & Alerts In Upcoming Version\e[0m" | tee -ai $INSTALLLOG
	echo -e "\033[34mEasyEngine (ee) Will NEVER Send Your Information Across\e[0m" | tee -ai $INSTALLLOG
fi
# Check Git User Is Empty Or Not
if [ -z "$EEGITNAME" ]
then
	read -p "Enter Your Name [$(whoami)]: " EEGITNAME
	# If Enter Is Pressed
	if [[ $EEGITNAME = "" ]]
	then
		EEGITNAME=$(whoami)
	fi
	git config --global user.name "$EEGITNAME" &>> $INSTALLLOG	
fi

# Check Git User Is Empty Or Not
if [ -z "$EEGITEMAIL" ]
then
	read -p "Enter Your Email [$(whoami)@$(hostname -f)]: " EEGITEMAIL
	# If Enter Is Pressed
	if [[ $EEGITEMAIL = "" ]]
	then
		EEGITEMAIL=$(whoami)@$(hostname -f)
	fi
	git config --global user.email $EEGITEMAIL &>> $INSTALLLOG
fi


# Source EasyEngine (ee) Auto Complete To Take Effect
echo
echo -e "\033[34mFor EasyEngine (ee) Auto Completion Run Following Command\e[0m" | tee -ai $INSTALLLOG
echo -e "\033[37msource /etc/bash_completion.d/ee\e[0m" | tee -ai $INSTALLLOG
echo

# Display Success Message
echo -e "\033[34mEasyEngine (ee) Installed Successfully\e[0m" | tee -ai $INSTALLLOG
echo -e "\033[34mEasyEngine (ee) Help: http://rtcamp.com/easyengine/docs/\e[0m" | tee -ai $INSTALLLOG
echo
