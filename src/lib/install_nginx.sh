# Install nginx

function install_nginx()
{
	echo_blue "Installing $NGINX_PACKAGE, please wait..."
	$APT_GET install $NGINX_PACKAGE || ee_error "Unable to install $NGINX_PACKAGE, exit status = " $?
}
