# Remove ViMbAdmin

function ee_ven_remove_vimbadmin()
{
	ee_lib_echo "Removing ViMbAdmin, please wait..."
	rm -rf /var/www/22222/htdocs/vimbadmin \
	|| ee_lib_error "Unable to remove ViMbAdmin, exit status = " $?
}
