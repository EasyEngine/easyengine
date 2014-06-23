# Enables WordPress debug mode

function ee_mod_debug_wordpress_start()
{
	if [ -e /var/www/$EE_DOMAIN/wp-config.php ]; then
		grep "'WP_DEBUG'" /var/www/$EE_DOMAIN/wp-config.php | grep true &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			# Debug WordPress
			ee_lib_echo "Start WordPress debug logs for $EE_DOMAIN"

			# Create debug.log & change permission
			touch /var/www/$EE_DOMAIN/htdocs/wp-content/debug.log
			chown $EE_PHP_USER:$EE_PHP_USER /var/www/$EE_DOMAIN/htdocs/wp-content/debug.log
		
			# Turn on debug
			sed -i "s/define('WP_DEBUG'.*/define('WP_DEBUG', true);\ndefine('WP_DEBUG_DISPLAY', false);\ndefine('WP_DEBUG_LOG', true);\ndefine('SAVEQUERIES', true);/" \
			/var/www/$EE_DOMAIN/wp-config.php

			# Install developer plugin
			cd /var/www/$EE_DOMAIN/htdocs/
			ee_lib_echo "Installing developer plugin, please wait..."
			wp plugin --allow-root install developer &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to install developer plugin, exit status = " $?
		else
			ee_lib_echo "WordPress debug log already started for $EE_DOMAIN"
		fi
	else
		ee_lib_echo_fail "Unable to find wp-config.php file, seems like not WordPress site"
	fi
}
