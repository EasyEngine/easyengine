#!/bin/bash

# Choose whenether to use apt-get or aptitude (aptitude recommended for latest debian versions and specially recommended for beginners)
# TODO: ask for dev or stable mariadb version
EEAPT="aptitude"

# Colors:
RED="\033[31m"
WHITE="\033[37m"
BLUE="\033[34m"
ENDCOL="\033[0m"

# Checking Linux Distro Is Debian
if [ -f /etc/lsb-release ]
then
	echo -e $RED"This Version Of EasyEngine (ee) Is Made For Debian Only As Of Now$ENDCOL"
	echo -e $RED"You can clone ubuntu version at: https://github.com/rtCamp/easyengine/fork$ENDCOL"
	exit 100
elif [ ! -f /etc/debian_version ]
then
	echo -e $RED"This Version Of EasyEngine (ee) Is Made For Debian Or Ubuntu Only As Of Now$ENDCOL"
	echo -e $RED"You Are Free To Fork this version at$BLUE\https://github.com/Mermouy/easyengine$ENDCOL\nOr grep the original ubuntu made EasyEngine (ee):$BLUE\https://github.com/rtCamp/easyengine/fork$ENDCOL"
	exit 100
fi

# Checking Permissions
Permission=$(id -u)
if [ $Permission -ne 0 ] 
then
	echo -e $RED"Privilege Required...$ENDCOL"
#	echo -e $RED"Uses:$BLUE\curl -sL rt.cx/ee | bash$ENDCOL"
	exit 100 
fi

# Make Variables Available For Later Use
LOGDIR=/var/log/easyengine
INSTALLLOG=/var/log/easyengine/install.log
VERSION=0.0.1

# Capture Errors
OwnError()
{
	echo -e "[ `date` ] $RED$@$ENDCOL" | tee -ai $INSTALLLOG
	exit 101
}

### Pre Checks To Avoid Later Screw Ups

# Checking Logs Directory
if [ ! -d $LOGDIR ]
then
	echo -e $BLUE"Creating EasyEngine (ee) Log Directory, Please Wait...$ENDCOL"
	mkdir -p $LOGDIR || OwnError "Unable To Create Log Directory $LOGDIR."
fi

# Checking Tee
if [ ! -x  /usr/bin/tee ]
then
	echo -e $RED"Tee Command Not Found$ENDCOL"
	echo -e $BLUE"Installing Tee, Please Wait...$ENDCOL"
	$EEAPT -y install coreutils &>> $INSTALLLOG || OwnError "Unable to install tee."
fi

echo &>> $INSTALLLOG
echo &>> $INSTALLLOG
echo -e $BLUE"EasyEngine (ee) version:$VERSION\nInstallation Started [$(date)]$ENDCOL" | tee -ai $INSTALLLOG

# Checking if required packages exist and install if not
REQUIREDLIST="ed wget tar curl git"
for i in $REQUIREDLIST
do
	if [ ! -x /usr/bin/$i ] && [ ! -x /bin/$i ]
	then
		echo -e "$RED$i Command Not Found$ENDCOL"
		echo "$i" > /tmp/requiredlist || OwnError "Unable to write required packages list to install."
		rm /tmp/requiredlist
	fi
done

if [ ! -z `cat /tmp/requiredlist` ]
then
	echo -e $BLUE"Installing `cat /tmp/requiredlist`, Please Wait...$ENDCOL"
	sed -i 's/git/git-core/' /tmp/requiredlist
	$EEAPT -y install `cat /tmp/requiredlist` || OwnError "Unable to install required packages."
fi

# Checking Name Servers
if [[ -z $(cat /etc/resolv.conf 2> /dev/null | awk '/^nameserver/ { print $2 }') ]]
then
	echo -e $RED"No Name Servers Detected$ENDCOL" | tee -ai $INSTALLLOG
	echo -e $RED"Please Configure /etc/resolv.conf$ENDCOL" | tee -ai $INSTALLLOG
	exit 102
fi

### Pre Checks End

# Check The EasyEngine (ee) Is Available
EXIST=$(basename `pwd`)
if [ "$EXIST" != "easyengine" ]
then
	echo -e $BLUE"Cloning EasyEngine (ee), Please Wait...$ENDCOL" | tee -ai $INSTALLLOG
	
	# Remove Older EasyEngine (ee) If Found
	rm -rf /tmp/easyengine &>> /dev/null

	# Clone EasyEngine (ee) Stable Repository
	git clone git://github.com/Mermouy/easyengine.git /tmp/easyengine &>> $INSTALLLOG || OwnError "Unable To Clone Easy Engine"
else
	cp -r . /tmp/easyengine
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
echo -e $BLUE"Installing EasyEngine (ee), Please Wait...$ENDCOL" | tee -ai $INSTALLLOG

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
EEGITNAME=$(git config --list | grep name | cut -d'=' -f2)
EEGITEMAIL=$(git config --list | grep email | cut -d'=' -f2)

if [ -z $EEGITNAME ] || [ -z $EEGITEMAIL ]
then
	echo
	echo -e $BLUE"EasyEngine (ee) Required Your Name & Email Address To Track Changes You Made Under The Git$ENDCOL" | tee -ai $INSTALLLOG
	echo -e $BLUE"EasyEngine (ee) Will Be Able To Send You Daily Reports & Alerts In Upcoming Version$ENDCOL" | tee -ai $INSTALLLOG
	echo -e $BLUE"EasyEngine (ee) Will Never Send Your Information Across$ENDCOL" | tee -ai $INSTALLLOG
fi
# Check Git User Is Empty Or Not
if [ -z $EEGITNAME ]
then
	read -p "Enter Your Name [$(whoami)]: " EEGITNAME
	# If Enter Is Pressed
	if [[ $EEGITNAME = "" ]]
	then
		EEGITNAME=$(whoami)
	fi
	git config --global user.name "$EEGITNAME" &>> $INSTALLLOG	
fi

# Check Git User Email Is Empty Or Not
if [ -z $EEGITEMAIL ]
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
echo -e $BLUE"For EasyEngine (ee) Auto Completion Run Following Command$ENDCOL" | tee -ai $INSTALLLOG
echo -e $WHITE"source /etc/bash_completion.d/ee$ENDCOL" | tee -ai $INSTALLLOG
echo

# Display Success Message
echo -e $BLUE"EasyEngine (ee) Installed Successfully$ENDCOL" | tee -ai $INSTALLLOG
echo -e $BLUE"EasyEngine (ee) Help: http://rtcamp.com/easyengine/docs/$ENDCOL" | tee -ai $INSTALLLOG
echo
