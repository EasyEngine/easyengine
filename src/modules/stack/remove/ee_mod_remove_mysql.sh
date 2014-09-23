# Remove MySQL package

function ee_mod_remove_mysql()
{
	dpkg --get-selections | grep -v deinstall | grep mysql-server &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		ee_mysql_server=mysql-server
	else
		ee_mysql_server=percona-server-server-5.6
	fi
	ee_lib_echo "$EE_SECOND Percona MySQL package, please wait..."
	$EE_APT_GET $EE_SECOND $ee_mysql_server mysqltuner percona-toolkit \
	|| ee_lib_error "Unable to $EE_SECOND Percona MySQL, exit status = " $?

	# Remove tuning-primer.sh
	rm -f /usr/local/bin/tuning-primer.sh
}
