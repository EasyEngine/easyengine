# WordPress debug

function ee_mod_debug_wp()
{
	if [ "$EE_DEBUG" = "--start" ]; then
		if [ -e /var/www/$EE_DOMAIN/wp-config.php ]; then
			grep "'WP_DEBUG'" /var/www/$EE_DOMAIN/wp-config.php | grep true &>> $EE_COMMAND_LOG
			if [ $? -ne 0 ]; then
				ee_lib_echo "Enable WordPress debug logs for $EE_DOMAIN, please wait..."

				# Create debug.log and fix permission
				touch /var/www/$EE_DOMAIN/htdocs/wp-content/debug.log
				chown $EE_PHP_USER:$EE_PHP_USER /var/www/$EE_DOMAIN/htdocs/wp-content/debug.log

				# Turn on
				sed -i "s/define('WP_DEBUG'.*/define('WP_DEBUG', true);\ndefine('WP_DEBUG_DISPLAY', false);\ndefine('WP_DEBUG_LOG', true);\ndefine('SAVEQUERIES', true);/" /var/www/$EE_DOMAIN/wp-config.php \
				|| ee_lib_error "Unable to activate WordPress debug logs, exit status = " $?

				# Install developer plugin
				ee_lib_echo "Installing developer plugin, please wait..."
				cd /var/www/$EE_DOMAIN/htdocs/ && \
				wp plugin --allow-root install developer &>> $EE_COMMAND_LOG \
				|| ee_lib_error "Unable to install developer plugin, exit status = " $?
				
				# Fix Developer plugin permissions
				chown -R $EE_PHP_USER:$EE_PHP_USER /var/www/$EE_DOMAIN/htdocs/wp-content/plugins/developer \
				|| ee_lib_error "Unable to change ownership for developer plugin, exit status = " $?

			else
				# Display message
				ee_lib_echo "WordPress debug log already enabled for $EE_DOMAIN"
			fi

			# Debug message
			EE_DEBUG_MSG="$EE_DEBUG_MSG /var/www/$EE_DOMAIN/htdocs/wp-content/debug.log"
		else
			# Display message
			ee_lib_echo_fail "Unable to find /var/www/$EE_DOMAIN/wp-config.php"
		fi
	elif [ "$EE_DEBUG" = "--stop" ]; then
		if [ -e /var/www/$EE_DOMAIN/wp-config.php ]; then
			grep "'WP_DEBUG'" /var/www/$EE_DOMAIN/wp-config.php | grep true &>> $EE_COMMAND_LOG
			if [ $? -eq 0 ]; then
				ee_lib_echo "Disable WordPress debug logs for $EE_DOMAIN, please wait..."

				# Turn off
				sed -i "s/define('WP_DEBUG', true);/define('WP_DEBUG', false);/" /var/www/$EE_DOMAIN/wp-config.php \
				&& sed -i "/define('WP_DEBUG_DISPLAY', false);/d" /var/www/$EE_DOMAIN/wp-config.php \
				&& sed -i "/define('WP_DEBUG_LOG', true);/d" /var/www/$EE_DOMAIN/wp-config.php \
				&& sed -i "/define('SAVEQUERIES', true);/d" /var/www/$EE_DOMAIN/wp-config.php \
				|| ee_lib_error "Unable to disable WordPress debug logs, exit status = " $?
			else
				# Display message
				ee_lib_echo "WordPress debug log already disabled for $EE_DOMAIN"
			fi
		else
			# Display message
			ee_lib_echo_fail "Unable to find /var/www/$EE_DOMAIN/wp-config.php"
		fi
	fi
}
