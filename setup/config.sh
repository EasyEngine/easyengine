#!/bin/bash



# Collect The Data To Make Configuration File
CONFIG=config.txt

# Remove Previous Configuration File
if [ -f config.txt ]
then
	rm config.txt
	# Move Previous Configuration File To Get MYSQL Settings
	#mv config.txt .config.txt
fi

# Send Global Data To Configuration File
#echo "[Global]" &>> $CONFIG
#echo "Htdocs: htdocs" &>> $CONFIG
#echo "Logs: logs" &>> $CONFIG
#echo "[/Global]" &>> $CONFIG


# Get The Domain Name Information
read -p "Enter The Domain Name (without http & www): " DOMAINNAME
echo "[Domain Name]" &>> $CONFIG
echo "		Domain Name: $DOMAINNAME" &>> $CONFIG
echo "[/Domain Name]" &>> $CONFIG
echo "" &>> $CONFIG
echo "" &>> $CONFIG

# Get The MySQL Username/Password
read -p "MySQL Host [localhost]: " TEMP
read -p "Enter The MySQL Username: " MYSQLUSER
read -p "Enter The MySQL Password: " MYSQLPASS
read -p "Enter The MySQL New Database Name For Wordpress: " WPDBNAME

# If User Pressed Enter Then Used localhost as MySQL Host
if [[ $TEMP = "" ]]
then
	MYSQLHOST=localhost
else
	MYSQLHOST=$TEMP
fi

# Send MySQL Data To Configuration File
echo "[MySQL]" &>> $CONFIG
echo "		MySQL Host: $MYSQLHOST" &>> $CONFIG	
echo "		MySQL Username: $MYSQLUSER" &>> $CONFIG
echo "		MySQL Password: $MYSQLPASS" &>> $CONFIG
echo "		WP Database: $WPDBNAME" &>> $CONFIG
echo "[/MySQL]" &>> $CONFIG
echo "" &>> $CONFIG
echo "" &>> $CONFIG


# Find Out Nginx User
NGINXUSER=$(grep user /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)

# Prompt User To Chnage Nginx User
# If User Press Enter Then Use Default Nginx User
read -p  "Nginx User [$NGINXUSER]: " TEMP
if [[ $TEMP = "" ]]
then
	NGINXUSER=$NGINXUSER
else
	NGINXUSER=$TEMP
fi

# Send Ngix Data To Configuration File
echo "[Nginx]" &>> $CONFIG
echo "		Nginx Username: $NGINXUSER" &>> $CONFIG
echo "[/Nginx]" &>> $CONFIG
echo "" &>> $CONFIG
echo "" &>> $CONFIG
