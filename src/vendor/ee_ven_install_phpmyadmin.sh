# Install phpMyAdmin

function ee_ven_install_phpmyadmin()
{
	if [ ! -d /var/www/22222/htdocs/db/pma ]; then

		local ee_random=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 7 | head -n1)

		# Setup phpMyAdmin directory
		mkdir -p /var/www/22222/htdocs/db/pma/ \
		|| ee_lib_error "Unable to create phpMyAdmin directory: /var/www/22222/htdocs/db/pma/, exit status = " $?

		# Download phpMyAdmin
		ee_lib_echo "Downloading phpMyAdmin, please wait..."
		wget --no-check-certificate -cqO /var/www/22222/htdocs/db/pma/pma.tar.gz https://github.com/phpmyadmin/phpmyadmin/archive/STABLE.tar.gz \
		|| ee_lib_error "Unable to download phpMyAdmin, exit status = " $?

		# Extract phpMyAdmin
		tar --strip-components=1 -zxf  /var/www/22222/htdocs/db/pma/pma.tar.gz -C /var/www/22222/htdocs/db/pma/ \
		|| ee_lib_error "Unable to extract phpMyAdmin, exit status = " $?

		# Remove unwanted files
		rm -f /var/www/22222/htdocs/db/pma/pma.tar.gz

		# Setup phpMyAdmin
		cp -v /var/www/22222/htdocs/db/pma/config.sample.inc.php /var/www/22222/htdocs/db/pma/config.inc.php &>> $EE_COMMAND LOG \
		|| ee_lib_error "Unable to setup phpMyAdmin, exit status = " $?

		sed -i "s/a8b7c6d/$ee_random/" /var/www/22222/htdocs/db/pma/config.inc.php \
		|| ee_lib_error "Unable to setup phpMyAdmin, exit status = " $?

	fi
}
