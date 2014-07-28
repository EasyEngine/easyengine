# Install ViMbAdmin

function ee_ven_install_vimbadmin()
{
	# Install ViMbAdmin
	ee_lib_echo "Downloading ViMbAdmin, please wait..."
	wget -cqO /var/www/22222/htdocs/vimbadmin.tar.gz https://github.com/opensolutions/ViMbAdmin/archive/3.0.10.tar.gz \
	|| ee_lib_error "Unable to download ViMbAdmin, exit status = " $?

	ee_lib_echo "Installing ViMbAdmin, please wait..."
	mkdir -p /var/www/22222/htdocs/vimbadmin
	tar -zxf /var/www/22222/htdocs/vimbadmin.tar.gz -C /var/www/22222/htdocs/vimbadmin --strip-components=1 

	# Install Composer
	cd /var/www/22222/htdocs/vimbadmin
	curl -sS https://getcomposer.org/installer | php \
	|| ee_lib_error "Unable to install Composer, exit status = " $?
	php composer.phar install --prefer-dist --no-dev \
	|| ee_lib_error "Unable to install ViMbAdmin, exit status = " $?

	# Fix permissions
	chown -R $EE_PHP_USER:$EE_PHP_USER /var/www/22222/htdocs/vimbadmin \
	|| ee_lib_error "Unable to change ownership for ViMbAdmin, exit status = " $? 

	# Remove unwanted files
	rm -rf /var/www/22222/htdocs/vimbadmin.tar.gz /var/www/22222/htdocs/vimbadmin/composer.phar
}
