# Backup NGINX configuration & Database & Webroot

function ee_mod_site_backup()
{
		
		# Backup directory setup
		local ee_webroot=$(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/\/htdocs//')
		if [ ! -d $ee_webroot/backup/$EE_DATE ]; then
			mkdir -p 	$ee_webroot/backup/$EE_DATE || ee_lib_error "Fail to create backup directory, exit status =" $?
		fi

		ee_lib_echo "Backup location: $ee_webroot/backup/$EE_DATE"
		ee_lib_echo "Backup NGINX configuration, please wait..."
		# Backup $EE_DOMAIN NGINX configuration
		cp /etc/nginx/sites-available/$EE_DOMAIN $ee_webroot/backup/$EE_DATE/ \
		|| ee_lib_error "Failed: Backup NGINX configuration, exit status =" $?

		# Move htdocs
		if [ "$EE_SITE_CURRENT_OPTION" = "HTML" ] || [ "$EE_SITE_CURRENT_OPTION" = "PHP" ] || [ "$EE_SITE_CURRENT_OPTION" = "MYSQL" ]; then
			ee_lib_echo "Backup webroot, please wait..."
			mv $ee_webroot/htdocs $ee_webroot/backup/$EE_DATE/ \
			|| ee_lib_error "Failed: Backup webroot, exit status =" $?
			ee_lib_echo "Setting up webroot, please wait..."
			mkdir -p $ee_webroot/htdocs || ee_lib_error "Failed: Setting up webroot, exit status =" $?
		fi

		# Database backup
		if [ -f $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/') ]; then
			local ee_db_name=$(grep DB_NAME $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/' 2> /dev/null) | cut -d"'" -f4)
			ee_lib_echo "Backup Database, please wait..."
			mysqldump $ee_db_name > $ee_webroot/backup/$EE_DATE/${ee_db_name}.sql \
			|| ee_lib_error "Failed: Backup Database, exit status =" $?

			# Move ee-config.php and copy wp-config.php to backup
			if [ -f $ee_webroot/ee-config.php ]; then
				mv $ee_webroot/ee-config.php $ee_webroot/backup/$EE_DATE/ \
				|| ee_lib_error "Failed: Backup ee-config.php, exit status =" $?
			else
				cp $ee_webroot/wp-config.php $ee_webroot/backup/$EE_DATE/ \
				|| ee_lib_error "Failed: Backup wp-config.php, exit status =" $?
			fi
		fi
}
