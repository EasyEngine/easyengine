# Remove php5 package

function ee_mod_remove_php()
{
	ee_lib_echo "Removing php5, please wait..."
	$EE_APT_GET remove php5-common php5-mysqlnd php5-xmlrpc \
	php5-curl php5-gd php5-cli php5-fpm php5-imap php5-mcrypt php5-xdebug \
	php5-memcache memcached || ee_lib_error "Unable to remove php5, exit status = " $?
}
