# Install Adminer

function ee_ven_install_adminer()
{
	if [ ! -d /var/www/22222/htdocs/db/adminer ]; then
		
		# Setup Adminer
		mkdir -p /var/www/22222/htdocs/db/adminer/ \
		|| ee_lib_error "Unable to create Adminer directory: /var/www/22222/htdocs/db/adminer/, exit status = " $?

		# Download Adminer
		ee_lib_echo "Downloading Adminer, please wait..."
		wget --no-check-certificate -cqO /var/www/22222/htdocs/db/adminer/index.php http://downloads.sourceforge.net/adminer/adminer-${EE_ADMINER_VERSION}.php \
		|| ee_lib_error "Unable to download Adminer, exit status = " $?

	fi
}
