# Setup WP Super Cache

function ee_mod_plugin_wpsc()
{
	cd /var/www/$EE_DOMAIN/htdocs/
	ee_lib_echo "Installing WP Super Cache plugin, please wait..."
	wp plugin --allow-root install wp-super-cache &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to install WP Super Cache plugin, exit status = " $?

	# Activate WP Super Cache
	wp plugin --allow-root activate wp-super-cache $EE_NETWORK_ACTIVATE &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to activate WP Super Cache plugin, exit status = " $?
}
