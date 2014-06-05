# Remove phpMyAdmin

function ee_ven_remove_phpmyadmin()
{
	ee_lib_echo "Removing phpMyAdmin, please wait..."
	rm -rf /var/www/22222/htdocs/db/pma \
	|| ee_lib_error "Unable to remove phpMyAdmin, exit status = " $?
}
