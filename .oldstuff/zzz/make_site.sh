#!/bin/bash
#This script makes virtual host, site directories and log files
#Author : Pragati Sureka

if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "make_vsite:You need to run this script as root(or use sudo)"
    exit
fi

# Check for proper number of command line args.
if [ $# -ne 1 ]
then
    echo "Usage: `basename $0` example.com"
    echo "            <example.com should be replaced by actual domain name>"
    exit
fi

sed s/[[:blank:]]*//g $1 > $1

#Script Variables
WEBROOT="/var/www"
SKELFILE="/etc/nginx/sites-available/example.com"
VSITEDIR="/etc/nginx/sites-available"
ESITEDIR="/etc/nginx/sites-enabled"
USER="www-data"
GROUP="www-data"
SERVER_RELOAD="service nginx restart"
WP_ZIP="/home/rtcamp/wordpress/latest.zip"  #wordpress 3.0

#make directories and touch log files
mkdir $WEBROOT/$1
mkdir $WEBROOT/$1/htdocs
mkdir $WEBROOT/$1/logs
touch $WEBROOT/$1/logs/error.log
touch $WEBROOT/$1/logs/access.log

#download latest wordpress and extract it to proper location
cd $WEBROOT/$1
#wget www.wordpress.org/latest.zip
unzip -q -o $WP_ZIP
mv wordpress/* htdocs/
rm -rf $WEBROOT/$1/wordpress
#rm $WEBROOT/$1/latest.zip
chown -R $USER:$GROUP $WEBROOT/$1
#chmod g+rw -R $WEBROOT/$1

#create database
mysql -u USER -pPASS -e 'create database `'$1'` '

#create wp-config.php file
CONFIGSAMPLE=$WEBROOT/$1/htdocs/wp-config-sample.php
sed s/database_name_here/$1/ $CONFIGSAMPLE | sed s/username_here/USER/ | sed s/password_here/PASS/ > $WEBROOT/$1/htdocs/wp-config.php

#copy skeleton file and enable site
sed s/example.com/$1/ $SKELFILE > $VSITEDIR/$1
ln -s $VSITEDIR/$1 $ESITEDIR/
echo "127.0.0.1		$1" >> /etc/hosts
$SERVER_RELOAD 
if [ $? -ne 0 ]; then
    #rm -rf $WEBROOT/$1
    unlink $ESITEDIR/$1
    $SERVER_RELOAD
    sed -i".bak" '$d' /etc/hosts
    echo "ERROR CREATING PLEASE CONTACT pragati.sureka@rtcamp.com FOR ASSISTANCE!"
fi