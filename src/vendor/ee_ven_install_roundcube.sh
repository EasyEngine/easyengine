# Install Roundcube

function ee_ven_install_roundcube()
{
	# Install Roundcube dependencies
	ee_lib_echo "Installing Roundcube, please wait..."
	$EE_APT_GET install php-pear \
	|| ee_lib_error "Unable to install php-pear, exit status = " $?
	pear install Mail_Mime Net_SMTP Mail_mimeDecode Net_IDNA2-beta Auth_SASL Net_Sieve Crypt_GPG &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to install pear packages, exit status = " $?

	# Setup Roundcube directory
	mkdir -p /var/www/roundcubemail/{htdocs,logs}
	ee_lib_symbolic_link /var/log/nginx/roundcubemail.access.log /var/www/roundcubemail/logs/access.log
	ee_lib_symbolic_link /var/log/nginx/roundcubemail.error.log /var/www/roundcubemail/logs/error.log

	# Install Roundcube
	wget -cqO /var/www/roundcube.tar.gz https://github.com/roundcube/roundcubemail/releases/download/${EE_ROUNDCUBE_VERSION}/roundcubemail-${EE_ROUNDCUBE_VERSION}.tar.gz \
	|| ee_lib_error "Unable to download Roundcube, exit status = " $?

	tar -zxf /var/www/roundcube.tar.gz -C /var/www/roundcubemail/htdocs/ --strip-components=1 \
	|| ee_lib_error "Unable to extract Roundcube, exit status = " $?

	# Fix permissions
	chown -R $EE_PHP_USER:$EE_PHP_USER /var/www/roundcubemail \
	|| ee_lib_error "Unable to change ownership for ViMbAdmin, exit status = " $? 

	# Remove unwanted files
	rm -rf /var/www/roundcube.tar.gz /var/www/roundcubemail-1.0.1
}
