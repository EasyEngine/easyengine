# Install Roundcube

function ee_mod_install_roundcube()
{
	# Install Roundcube dependencies
	ee_lib_echo "Installing Roundcube dependencies, please wait..."
	$EE_APT_GET install php-pear \
	|| ee_lib_error "Unable to install php-pear, exit status = " $?
	pear install Mail_Mime Net_SMTP Mail_mimeDecode Net_IDNA2 Auth_SASL Net_Sieve Crypt_GPG \
	|| ee_lib_error "Unable to install pear packages, exit status = " $?

	# Setup Roundcube directory
	mkdir -p /var/www/roundcubemail/htdocs && mkdir -p /var/www/roundcubemail/logs
	ee_lib_symbolic_link /var/log/nginx/roundcubemail.access.log /var/www/roundcubemail/logs/access.log
	ee_lib_symbolic_link /var/log/nginx/roundcubemail.error.log /var/www/roundcubemail/logs/error.log

	# Install Roundcube
	ee_lib_echo "Downloading Roundcube, please wait..."
	wget -cqO /var/www/roundcube.tar.gz https://github.com/roundcube/roundcubemail/releases/download/1.0.2/roundcubemail-1.0.2.tar.gz \
	|| ee_lib_error "Unable to download Roundcube, exit status = " $?

	ee_lib_echo "Installing Roundcube, please wait..."
	tar -zxf /var/www/roundcube.tar.gz
	mv /var/www/roundcubemail-1.0.1/* /var/www/roundcubemail/htdocs/

	# Fix permissions
	chown -R $EE_PHP_USER:$EE_PHP_USER /var/www/roundcubemail \
	|| ee_lib_error "Unable to change ownership for ViMbAdmin, exit status = " $? 

	# Remove unwanted files
	rm -rf /var/www/roundcube.tar.gz /var/www/roundcubemail-1.0.1
}
