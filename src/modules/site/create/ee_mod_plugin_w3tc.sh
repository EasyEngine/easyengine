# Setup W3 Total Cache

function ee_mod_plugin_w3tc()
{
	cd /var/www/$EE_DOMAIN/htdocs/
	ee_lib_echo "Installing W3 Total Cache plugin, please wait..."
	wp plugin --allow-root install w3-total-cache &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to install W3 Total Cache plugin, exit status = " $?

	# Activate W3 Total Cache
	wp plugin --allow-root activate w3-total-cache $EE_NETWORK_ACTIVATE &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to activate W3 Total Cache plugin, exit status = " $?
}
