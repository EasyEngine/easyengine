# Remove MySQL package

function ee_mod_remove_mysql()
{
	ee_lib_echo "$EE_SECOND MySQL package, please wait..."
	$EE_APT_GET $EE_SECOND mysql-server mysqltuner percona-toolkit \
	|| ee_lib_error "Unable to $EE_SECOND MySQL, exit status = " $?

	# Remove tuning-primer.sh
	rm -f /usr/local/bin/tuning-primer.sh
}
