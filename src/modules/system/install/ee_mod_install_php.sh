# Install php5-fpm package

function ee_mod_install_php()
{
	ee_lib_echo "Installing PHP, please wait..."
	$EE_APT_GET install php5-common php5-mysqlnd php5-xmlrpc \
	php5-curl php5-gd php5-cli php5-fpm php5-imap php5-mcrypt php5-xdebug \
	php5-memcache memcached | tee -ai EE_COMMAND_LOG || ee_lib_error "Unable to install PHP5, exit status = " $?
}
