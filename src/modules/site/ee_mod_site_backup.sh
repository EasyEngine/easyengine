# Backup NGINX configuration & Database & Webroot

function ee_mod_site_backup()
{
		
		# Backup directory setup
		local ee_webroot=$(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/\/htdocs//')
		if [ ! -d $ee_webroot/backup ] || [ ! -d $ee_webroot/backup/htdocs/$EE_DATE ] || [ ! -d $ee_webroot/backup/nginx/$EE_DATE ] || [ ! -d $ee_webroot/backup/db/$EE_DATE ]; then
			mkdir -p 	$ee_webroot/backup/{htdocs/$EE_DATE,nginx/$EE_DATE,db/$EE_DATE} || ee_lib_error "Unable to create $ee_webroot/backup directory, exit status =" $?
		fi

		# Move htdocs
		if [ "$EE_SITE_CURRENT_OPTION" = "HTML" ] || [ "$EE_SITE_CURRENT_OPTION" = "MYSQL" ] || [ "$EE_SITE_CURRENT_OPTION" = "PHP" ]; then
			ee_lib_echo "Backup webroot at $ee_webroot/backup/htdocs/$EE_DATE/, please wait..."
			mv $ee_webroot/htdocs $ee_webroot/backup/htdocs/$EE_DATE/ || ee_lib_error "Unable to move $ee_webroot/htdocs to backup, exit status =" $?
			mkdir -p $ee_webroot/htdocs
		fi

		ee_lib_echo "Backup NGINX configuration at $ee_webroot/backup/nginx/$EE_DATE/, please wait..."
		# Backup $EE_DOMAIN NGINX configuration
		cp /etc/nginx/sites-available/$EE_DOMAIN $ee_webroot/backup/nginx/$EE_DATE/ &>> $EE_COMMAND_LOG

		# Database backup
		if [ -f $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/') ]; then
			local ee_db_name=$(grep DB_NAME $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/' 2> /dev/null) | cut -d"'" -f4)
			ee_lib_echo "Backup Database $ee_db_name at $ee_webroot/backup/db/$EE_DATE/, please wait..."
			mysqldump $ee_db_name > $ee_webroot/backup/db/$EE_DATE/${ee_db_name}.sql \
			|| ee_lib_error "Unable to dump ${ee_db_name}, exit status =" $?

			# Move ee-config.php and copy wp-config.php to backup
			if [ -f $ee_webroot/ee-config.php ]; then
				mv $ee_webroot/ee-config.php $ee_webroot/backup/htdocs/$EE_DATE/ || ee_lib_error "Unable to move $ee_webroot/ee-config.php to backup, exit status =" $?
			else
				cp $ee_webroot/wp-config.php $ee_webroot/backup/htdocs/$EE_DATE/ || ee_lib_error "Unable to move $ee_webroot/wp-config.php to backup, exit status =" $?
			fi
		fi
}
