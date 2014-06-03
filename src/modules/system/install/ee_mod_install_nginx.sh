# Install nginx package

function ee_mod_install_nginx()
{
	ee_lib_echo "Installing $NGINX_PACKAGE, please wait..."
	$EE_APT_GET install $NGINX_PACKAGE || ee_lib_error "Unable to install $NGINX_PACKAGE, exit status = " $?
}
