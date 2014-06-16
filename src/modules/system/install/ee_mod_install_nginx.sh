# Install nginx package

function ee_mod_install_nginx()
{
	ee_lib_echo "Installing $EE_NGINX_PACKAGE, please wait..."
	$EE_APT_GET install $EE_NGINX_PACKAGE | tee -ai EE_COMMAND_LOG \
	|| ee_lib_error "Unable to install $EE_NGINX_PACKAGE, exit status = " $?
}
