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
			mv $ee_webroot/htdocs $ee_webroot/backup/htdocs/htdocs-$(date +"%m-%d-%y:") || ee_lib_error "Unable to create $ee_webroot/htdocs backup, exit status =" $?
			mkdir -p $ee_webroot/htdocs
		fi

		# Backup $EE_DOMAIN NGINX configuration
		cp -av /etc/nginx/sites-available/$EE_DOMAIN $ee_webroot/backup/nginx/${EE_DOMAIN}-$(date +"%m-%d-%y").conf.bak

		# Database backup
		if [ -f $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/') ]; then
			local ee_db_name=$(grep DB_NAME $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed 's/htdocs/*-config.php/' 2> /dev/null) | cut -d"'" -f4)
			mysqldump $ee_db_name > $ee_webroot/backup/db/${ee_db_name}-$(date +"%m-%d-%y").sql.bak
		fi
}
