# Remove Roundcube

function ee_ven_remove_roundcube()
{
	ee_lib_echo "Removing Roundcube, please wait..."
	rm -rf /var/www/22222/htdocs/vimbadmin \
	|| ee_lib_error "Unable to remove Roundcube, exit status = " $?
}
