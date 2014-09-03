# Remove ViMbAdmin

function ee_ven_remove_vimbadmin()
{
	ee_lib_echo "Removing ViMbAdmin, please wait..."

	mysql -e "drop database \`vimbadmin\`" &>> $EE_COMMAND_LOG
	mysql -e "drop user vimbadmin@'$EE_MYSQL_GRANT_HOST'" &>> $EE_COMMAND_LOG

	ee_lib_echo "Removing ViMbAdmin PHP dependencies, please wait..."
	$EE_APT_GET $EE_SECOND php5-cgi php-gettext \
	||ee_lib_error "Unable to $EE_SECOND ViMbAdmin PHP dependencies, exit status = " $?

	rm -rf /var/www/22222/htdocs/vimbadmin \
	|| ee_lib_error "Unable to remove ViMbAdmin, exit status = " $?
}
