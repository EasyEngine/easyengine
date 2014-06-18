# Adjust permission

function ee_lib_permissions()
{
	ee_lib_echo "Changing ownership of /var/www/$EE_DOMAIN, please wait..."
	chown -R $EE_PHP_USER:$EE_PHP_USER /var/www/$EE_DOMAIN/ \
	|| ee_lib_error "Unable to change ownership for $EE_DOMAIN, exit status = " $?
}
