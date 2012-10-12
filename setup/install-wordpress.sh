#!/bin/bash



# Capture Errors
OwnError()
{
	#echo $@ >&2
	clear
	echo -e "[ $0 ][ `date` ] \033[31m $@ \e[0m" | tee -ai /var/log/easyengine/error.log
	exit 100
}


# Collect Data From Configuration File
WORDPRESSPATH="/var/www/$(grep 'Domain Name:' config.txt | cut -d':' -f2 | cut -d' ' -f2)/htdocs"
MYSQLHOST=$(grep "MySQL Host:" config.txt | cut -d':' -f2 | cut -d' ' -f2)
MYSQLUSER=$(grep "MySQL Username:" config.txt | cut -d':' -f2 | cut -d' ' -f2)
MYSQLPASS=$(grep "MySQL Password:" config.txt | cut -d':' -f2 | cut -d' ' -f2)
WPDBNAME=$(grep "WP Database:" config.txt | cut -d':' -f2 | cut -d' ' -f2)
NGINXUSER=$(grep "Nginx Username:" config.txt | cut -d':' -f2 | cut -d' ' -f2)

#echo $WORDPRESSPATH
#echo $MYSQLHOST
#echo $MYSQLUSER
#echo $MYSQLPASS
#echo $WPDBNAME
#echo $NGINXUSER
#exit


# Cheking WordPress Path Exist
if [ ! -d $WORDPRESSPATH ]
then
	echo -e "\033[31m $WORDPRESSPATH Not Exist !! \e[0m"
	echo -e "\033[34m Making Directory For $WORDPRESSPATH \e[0m"
	mkdir -p $WORDPRESSPATH
fi

# Install Wordpress
cd $WORDPRESSPATH
wget -c http://wordpress.org/latest.tar.gz
tar --strip-components=1 -zxvf latest.tar.gz
rm latest.tar.gz


# Creating MySQL Database For Wordpress
mysql -u $MYSQLUSER -h $MYSQLHOST -p$MYSQLPASS -e 'create databse `$WPDBNAME`'

# Grant Ownership To Nginx User
sudo chown -R $NGINXUSER:$NGINXUSER $WORDPRESSPATH
