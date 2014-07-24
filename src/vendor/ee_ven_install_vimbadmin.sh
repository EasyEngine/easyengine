# Install ViMbAdmin

function ee_mod_install_vimbadmin()
{
	# Install ViMbAdmin
	ee_lib_echo "Downloading ViMbAdmin, please wait..."
	wget -cqO /var/www/22222/htdocs/vimbadmin.tar.gz https://github.com/opensolutions/ViMbAdmin/archive/3.0.10.tar.gz \
	|| ee_lib_error "Unable to download ViMbAdmin, exit status = " $?

	ee_lib_echo "Installing ViMbAdmin, please wait..."
	tar -zxf /var/www/22222/htdocs/vimbadmin.tar.gz
	mv /var/www/22222/htdocs/ViMbAdmin-3.0.10 /var/www/22222/htdocs/vimbadmin

	# Install Composer
	cd /var/www/22222/htdocs/vimbadmin
	curl -sS https://getcomposer.org/installer | php \
	|| ee_lib_error "Unable to install Composer, exit status = " $?
	php composer.phar install --prefer-dist --no-dev \
	|| ee_lib_error "Unable to install ViMbAdmin, exit status = " $?

	# Remove unwanted files
	rm -rf /var/www/22222/htdocs/vimbadmin.tar.gz /var/www/22222/htdocs/vimbadmin/composer.phar
}
