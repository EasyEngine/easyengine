# Backup NGINX configuration & Database & Webroot

function ee_mod_site_backup()
{
		# Backup directory setup
		local ee_webroot=$(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/\/htdocs//')
		if [ ! -d $ee_webroot/backup ] || [ ! -d $ee_webroot/backup/htdocs ] || [ ! -d $ee_webroot/backup/nginx ] || [ ! -d $ee_webroot/backup/db ]; then
			mkdir -p 	$ee_webroot/backup/{htdocs,nginx,db} || ee_lib_error "Unable to create $ee_webroot/backup directory, exit status =" $?
		fi

		# Move htdocs
		if [ "$EE_SITE_CURRENT_OPTION" = "HTML" ] || [ "$EE_SITE_CURRENT_OPTION" = "MYSQL" ] || [ "$EE_SITE_CURRENT_OPTION" = "PHP" ]; then
			ee_lib_echo "Creating Webroot backup for $EE_DOMAIN before updating ..."
			mv $ee_webroot/htdocs $ee_webroot/backup/htdocs/$(date +%d%b%Y%H%M%S)/ || ee_lib_error "Unable to move $ee_webroot/htdocs to backup, exit status =" $?
			mkdir -p $ee_webroot/htdocs
		fi

		ee_lib_echo "Creating NGINX configuration backup for $EE_DOMAIN before updating ..."
		# Backup $EE_DOMAIN NGINX configuration
		cp -av /etc/nginx/sites-available/$EE_DOMAIN $ee_webroot/backup/nginx/${EE_DOMAIN}-$(date +%d%b%Y%H%M%S).conf.bak &>> $EE_COMMAND_LOG

		# Database backup
		if [ -f $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/') ]; then
			ee_lib_echo "Creating Database backup for $EE_DOMAIN before updating ..."
			local ee_db_name=$(grep DB_NAME $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/' 2> /dev/null) | cut -d"'" -f4)
			mysqldump $ee_db_name > $ee_webroot/backup/db/${ee_db_name}-$(date +%d%b%Y%H%M%S).sql.bak &>> $EE_COMMAND_LOG

			# Move ee-config.php and wp-config.php
			mv $ee_webroot/*-config.php $ee_webroot/backup/htdocs/$(date +%d%b%Y%H%M%S)/ || ee_lib_error "Unable to move $ee_webroot/*-config.php to backup, exit status =" $?
		fi
}
