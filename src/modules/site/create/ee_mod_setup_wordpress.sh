# Setup WordPress for $EE_DOMAIN

function ee_mod_setup_wordpress()
{
	# Download latest WordPress
	ee_lib_echo "Downloading WordPress, please wait..."
	wget --no-check-certificate -cqO /var/www/$EE_DOMAIN/htdocs/latest.tar.gz  \
	http://wordpress.org/latest.tar.gz \
	|| ee_lib_error "Unable to download WordPress, exit status = " $?

	# Extracting WordPress
	tar --strip-components=1 -zxf /var/www/$EE_DOMAIN/htdocs/latest.tar.gz \
	-C /var/www/$EE_DOMAIN/htdocs/ \
	|| ee_lib_error "Unable to extract WordPress, exit status = " $?

	# Removing WordPress archive
	rm /var/www/$EE_DOMAIN/htdocs/latest.tar.gz

	# Default WordPress prefix or custom prefix
	if [ $($EE_CONFIG_GET wordpress.prefix) == "true" ];then
		read -p "Enter the MySQL database table prefix [wp_]: " EE_WP_PREFIX
		# Display EE_WP_PREFIX valid characters warning & try again
		while [[ ! ($EE_WP_PREFIX  =~ ^[A-Za-z0-9_]*$) ]];	do
			echo "Warning: table prefix can only contain numbers, letters, and underscores"
			read -p "Enter the MySQL database table prefix [wp_]: " EE_WP_PREFIX
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

	# Database setup
	ee_mod_setup_database

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

	# WordPress default user: admin
	EE_WP_USER=$($EE_CONFIG_GET wordpress.user)
	if [[ $EE_WP_USER = "" ]]; then
		EE_WP_USER=admin
	fi

	# WordPress default user: admin
	EE_WP_PASS=$($EE_CONFIG_GET wordpress.password)
	if [[ $EE_WP_PASS = "" ]]; then
		EE_WP_PASS=$EE_RANDOM
	fi
	
	# WordPress default email: `git config user.email`
	EE_WP_EMAIL=$($EE_CONFIG_GET wordpress.email)
	if [[ $EE_WP_EMAIL = "" ]]; then
		EE_WP_EMAIL=$(git config user.email)
	fi

	# Create WordPress tables
	ee_lib_echo "Setting up WordPress, please wait..."
	cd /var/www/$EE_DOMAIN/htdocs \
	|| ee_lib_error "Unable to change directory to install WordPress, exit status = " $?
	
	wp core install --allow-root  --url=$EE_WWW_DOMAIN --title="$EE_WWW_DOMAIN" \
	--admin_name=$EE_WP_USER --admin_password=$EE_WP_PASS --admin_email=$EE_WP_EMAIL &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to create WordPress tables for $EE_DOMAIN, exit status = " $?
	
	# Update WordPress permalink structure day and postname
	ee_lib_echo "Updating WordPress permalink, please wait..."
	wp rewrite structure --allow-root /%year%/%monthnum%/%day%/%postname%/ &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to update WordPress permalink for $EE_DOMAIN, exit status = " $?
}
