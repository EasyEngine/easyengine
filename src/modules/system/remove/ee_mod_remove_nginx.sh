# Remove nginx package

function ee_mod_remove_nginx()
{
	ee_lib_echo "Removing $EE_NGINX_PACKAGE, please wait..."
	$EE_APT_GET remove $EE_NGINX_PACKAGE nginx-common \
	|| ee_lib_error "Unable to remove $NGINX_PACKAGE, exit status = " $?
}
