# Remove MySQL package

function ee_mod_remove_mysql()
{
	ee_lib_echo "Removing MySQL, please wait..."
	$EE_APT_GET remove mysql-server mysqltuner percona-toolkit \
	|| ee_lib_error "Unable to remove MySQL, exit status = " $?

	# Remove tuning-primer.sh
	rm -f /usr/local/bin/tuning-primer.sh
}
