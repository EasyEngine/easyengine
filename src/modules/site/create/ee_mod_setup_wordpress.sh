# Setup WordPress for $EE_DOMAIN

function ee_mod_setup_wordpress()
{
	# Random characters
	local ee_random=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)

	# Download latest WordPress
	ee_lib_echo "Downloading WordPress, please wait..."
	cd /var/www/$EE_DOMAIN/htdocs && wp --allow-root core download &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to download WordPress, exit status = " $?
	
	# Database setup
	# if EE_DB_NAME, EE_DB_USER, EE_DB_PASS are empty then setup database for new site
	# else current mysql site is to be updated
	if [ "$EE_DB_NAME" = "" ] && [ "$EE_DB_USER" = "" ] && [ "$EE_DB_PASS" = "" ]; then
		ee_mod_setup_database
	else
		# Display when run ee site update mysql.com --wp
		echo -e "EE_DB_NAME = $EE_DB_NAME \nEE_DB_USER = $EE_DB_USER \nEE_DB_PASS = $EE_DB_PASS \nEE_MYSQL_HOST = $EE_MYSQL_HOST \nEE_MYSQL_GRANT_HOST = $EE_MYSQL_GRANT_HOST" &>> $EE_COMMAND_LOG
	fi
	
	# Default WordPress prefix or custom prefix
	if [ $($EE_CONFIG_GET wordpress.prefix) == "true" ];then
		read -p "Enter the WordPress table prefix [wp_]: " EE_WP_PREFIX
		# Display EE_WP_PREFIX valid characters warning & try again
		while [[ ! ($EE_WP_PREFIX  =~ ^[A-Za-z0-9_]*$) ]];	do
			echo "Warning: table prefix can only contain numbers, letters, and underscores"
			read -p "Enter the WordPress table prefix [wp_]: " EE_WP_PREFIX
		done
	fi

	# If wordpress.prefix = false 
	# 		Then it never ask for WordPress prefix in this case $EE_WP_PREFIX is empty
	# If wordpress.prefix = true
	#		User enter custom WordPress prefix then $EE_WP_PREFIX is not empty & we used provided WordPress prefix
 	#		If user pressed enter then $EE_WP_PREFIX is empty
	
	# WordPress database table prefix default: wp_
	if [[ $EE_WP_PREFIX = "" ]];then
		EE_WP_PREFIX=wp_
	fi

	# Let's log WordPress table prefix
	echo EE_WP_PREFIX = $EE_WP_PREFIX &>> $EE_COMMAND_LOG

	# Modify wp-config.php & move outside the webroot
	cp /var/www/$EE_DOMAIN/htdocs/wp-config-sample.php \
	/var/www/$EE_DOMAIN/wp-config.php

	sed -i "s/database_name_here/$EE_DB_NAME/" \
	/var/www/$EE_DOMAIN/wp-config.php

	sed -i "s/username_here/$EE_DB_USER/" \
	/var/www/$EE_DOMAIN/wp-config.php
				
	sed -i "s/password_here/$EE_DB_PASS/" \
	/var/www/$EE_DOMAIN/wp-config.php

	sed -i "s/localhost/$EE_MYSQL_HOST/" \
	/var/www/$EE_DOMAIN/wp-config.php

	sed -i "s/wp_/$EE_WP_PREFIX/" \
	/var/www/$EE_DOMAIN/wp-config.php

	printf '%s\n' "g/put your unique phrase here/d" \
	a "$(curl -sL https://api.wordpress.org/secret-key/1.1/salt/)" . w \
	| ed -s /var/www/$EE_DOMAIN/wp-config.php

	# Set WordPress username
	# First get WordPress username from /etc/easyengine/ee.conf file
	EE_WP_USER=$($EE_CONFIG_GET wordpress.user)

	if [[ $EE_WP_USER = "" ]]; then
		git config user.name &>> /dev/null
		if [ $? -eq 0 ]; then
			# Set WordPress username from git config user.name
			EE_WP_USER=$(git config user.name)
		else
			while [ -z $EE_WP_USER ]; do
			# Ask user to provide WordPress username
			ee_lib_echo "Usernames can have only alphanumeric characters, spaces, underscores, hyphens, periods and the @ symbol."
			read -p "Enter WordPress username: " EE_WP_USER
			done
		fi
	fi

	# Set WordPress password
	EE_WP_PASS=$($EE_CONFIG_GET wordpress.password)
	if [[ $EE_WP_PASS = "" ]]; then
		EE_WP_PASS=$ee_random
	fi
	
	# Set WordPress email
	# First get WordPress email from /etc/easyengine/ee.conf file
	EE_WP_EMAIL=$($EE_CONFIG_GET wordpress.email)

	if [[ $EE_WP_EMAIL = "" ]]; then
		git config user.email &>> /dev/null
		if [ $? -eq 0 ]; then
			# Set WordPress email from git config user.email
			EE_WP_EMAIL=$(git config user.email)
		else
			while [ -z $EE_WP_EMAIL ]; do
			# Ask user to provide WordPress email
			read -p "Enter WordPress email: " EE_WP_EMAIL
			done
		fi
	fi

	# Let's log WordPress username/password/email
	echo -e "EE_WP_USER = $EE_WP_USER \nEE_WP_PASS = $EE_WP_PASS \nEE_WP_EMAIL = $EE_WP_EMAIL" &>> $EE_COMMAND_LOG

	# Create WordPress tables
	ee_lib_echo "Setting up WordPress, please wait..."
	cd /var/www/$EE_DOMAIN/htdocs \
	|| ee_lib_error "Unable to change directory to install WordPress, exit status = " $?
	
	wp core install --allow-root  --url=$EE_WWW_DOMAIN --title="$EE_WWW_DOMAIN" \
	--admin_name="$EE_WP_USER" --admin_password=$EE_WP_PASS --admin_email=$EE_WP_EMAIL &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to create WordPress tables for $EE_DOMAIN, exit status = " $?
	
	# Update WordPress permalink structure day and postname
	ee_lib_echo "Updating WordPress permalink, please wait..."
	wp rewrite structure --allow-root /%year%/%monthnum%/%day%/%postname%/ &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to update WordPress permalink for $EE_DOMAIN, exit status = " $?

	# Setup WordPress Network
	if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
		ee_mod_setup_network
	fi

	# Install WordPress plugins
	ee_mod_plugin_nginx_helper

	if [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
		ee_mod_plugin_wpsc
	fi

	if [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
		ee_mod_plugin_w3tc
	fi

}
