# Remove nginx package

function ee_mod_remove_nginx()
{
	ee_lib_echo "$EE_SECOND $EE_NGINX_PACKAGE package, please wait..."
	$EE_APT_GET $EE_SECOND $EE_NGINX_PACKAGE nginx-common \
	|| ee_lib_error "Unable to $EE_SECOND $NGINX_PACKAGE, exit status = " $?
}
