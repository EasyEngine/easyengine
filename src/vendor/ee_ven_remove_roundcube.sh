# Remove Roundcube

function ee_ven_remove_roundcube()
{
	ee_lib_echo "Removing Roundcube dependencies, please wait..."
	# Remove packages installed using Pear
	pear uninstall Mail_Mime Net_SMTP Mail_mimeDecode Net_IDNA2-beta Auth_SASL Net_Sieve Crypt_GPG &>> $EE_COMMAND_LOG

	# Remove Roundcube
	ee_lib_echo "Removing Roundcube, please wait..."

	mysql -e "drop database \`roundcubemail\`" &>> $EE_COMMAND_LOG
	mysql -e "drop user roundcube@'$EE_MYSQL_GRANT_HOST'" &>> $EE_COMMAND_LOG

	rm -rf /var/www/roundcubemail /etc/nginx/sites-available/webmail /etc/nginx/sites-enabled/webmail \
	|| ee_lib_error "Unable to remove Roundcube, exit status = " $?
}
