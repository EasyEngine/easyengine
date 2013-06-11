#!/bin/bash
#This script makes virtual host, site directories and log files
#Author : Pragati Sureka, rahul286

if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "make_vsite:You need to this script as root(or use sudo)"
    exit
fi

#Script Variables
WEBROOT="/var/www"
SKELFILE="/etc/apache2/sites-available/skeleton"
VSITEDIR="/etc/apache2/sites-available"
USER="www-data"
GROUP="www-data"
SERVER_RELOAD="/etc/init.d/apache2 reload"

#make directories and touch log files
mkdir $WEBROOT/$1
mkdir $WEBROOT/$1/htdocs
mkdir $WEBROOT/$1/logs
touch $WEBROOT/$1/logs/error.log
touch $WEBROOT/$1/logs/custom.log

#copy skeleton file and enable site
sed s/site/$1/ $SKELFILE > $VSITEDIR/$1
a2ensite $1 &> /dev/null
echo "127.0.0.1		$1" >> /etc/hosts
$SERVER_RELOAD &> /dev/null
if [ $? -ne 0 ]; then
    rm -rf $WEBROOT/$1
    a2dissite $1
    sed -i".bak" '$d' /etc/hosts
    echo "ERROR CREATING PLEASE CONTACT pragati.sureka@rtcamp.com FOR ASSISTANCE!"
fi
