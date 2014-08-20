# Setup Roundcube

function ee_ven_setup_roundcube()
{
	ee_lib_echo "configuring Roundcube, please wait..."

	# Random characters
	local ee_random=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)

  # Setting up database for Roundcube
	mysql -e "create database \`roundcubemail\`" \
	|| ee_lib_error "Unable to create Roundcube database, exit status = " $?

	# Create MySQL user
	mysql -e "grant all privileges on roundcubemail.* to roundcube@'$EE_MYSQL_GRANT_HOST' IDENTIFIED BY '$ee_random'"  \
	|| ee_lib_error "Unable to grant privileges for Roundcube database user, exit status = " $?
	mysql -e "flush privileges"	

	# Import Roundcube initial database
	mysql roundcubemail < /var/www/roundcubemail/htdocs/SQL/mysql.initial.sql \
	|| ee_lib_error "Unable to import database for Roundcube, exit status = " $?

	# Setup configuration for Roundcube
	cp -av /var/www/roundcubemail/htdocs/config/config.inc.php.sample /var/www/roundcubemail/htdocs/config/config.inc.php &>> $EE_COMMAND_LOG
	sed -i "s'mysql://roundcube:pass@localhost/roundcubemail'mysql://roundcube:${ee_random}@${EE_MYSQL_HOST}/roundcubemail'" /var/www/roundcubemail/htdocs/config/config.inc.php \
	|| ee_lib_error "Unable to setup Roundcube database details in config.inc.php file, exit status = " $?	

	# Setup Nginx configuration to access Webmail
	cp -v /usr/share/easyengine/mail/webmail /etc/nginx/sites-available/ &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to copy Nginx configuration for Roundcube, exit status = " $?

	ln -sf /etc/nginx/sites-available/webmail /etc/nginx/sites-enabled/ \
	|| ee_lib_error "Unable to create softlink for Webmail, exit status = " $?

}
