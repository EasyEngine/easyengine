# Setup ViMbAdmin

function ee_mod_setup_vimbadmin()
{
	# Random characters
	local ee_random=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)

  # Setting up database for ViMbAdmin
	mysql -e "create database \`vimbadmin\`" \
	|| ee_lib_error "Unable to create ViMbAdmin database, exit status = " $?

	# Create MySQL User
	mysql -e "create user 'vimbadmin'@'$EE_MYSQL_HOST' identified by '$ee_random'" \
	|| ee_lib_error "Unable to create ViMbAdmin database user, exit status = " $?
	mysql -e "flush privileges"	

	# Setup configuration for ViMbAdmin
	cp -v /var/www/22222/htdocs/vimbadmin/application/configs/application.ini.dist /var/www/22222/htdocs/vimbadmin/application/configs/application.ini

	sed -i "s/defaults.mailbox.uid = 2000/defaults.mailbox.uid = 5000/" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini
	sed -i "s/defaults.mailbox.gid = 2000/defaults.mailbox.gid = 5000/" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini
	sed -i "s/maildir:\/srv\/vmail\/%d\/%u\/mail:LAYOUT=fs/maildir:\/var\/vmail\/%d\/%u/" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini
	sed -i "s'/srv/vmail/%d/%u'/var/vmail/'" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini
	sed -i "s/pdo_mysql/mysqli/" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini
	sed -i "s/'xxx'/'$ee_random'/" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini
	sed -i "s/resources.doctrine2.connection.options.host     = 'localhost'/resources.doctrine2.connection.options.host     = '$EE_MYSQL_HOST'/" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini
	sed -i "s/defaults.mailbox.password_scheme = \"md5.salted\"/defaults.mailbox.password_scheme = \"md5\"/" /var/www/22222/htdocs/vimbadmin/application/configs/application.ini

  
