# Install phpMyAdmin

function ee_ven_install_pma()
{
	if [ ! -d /var/www/22222/htdocs/db/pma ]; then

		# Setup phpMyAdmin
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

	fi
}
