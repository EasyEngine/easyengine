# Remove Adminer

function ee_ven_remove_adminer()
{
	ee_lib_echo "Removing Adminer, please wait..."
	rm -rf /var/www/22222/htdocs/db/adminer \
	|| ee_lib_error "Unable to remove Adminer, exit status = " $?
}
