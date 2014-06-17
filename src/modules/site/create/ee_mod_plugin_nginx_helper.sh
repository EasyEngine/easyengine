# Setup NGINX Helper

function ee_mod_plugin_nginx_helper()
{
	cd /var/www/$EE_DOMAIN/htdocs/
	ee_lib_echo "Installing Nginx Helper plugin, please wait..."
	wp plugin --allow-root install nginx-helper &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to install Nginx Helper plugin, exit status = " $?

	# Activate Nginx Helper
	wp plugin --allow-root activate nginx-helper $EE_NETWORK_ACTIVATE &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to activate Nginx Helper plugin, exit status = " $?
}
