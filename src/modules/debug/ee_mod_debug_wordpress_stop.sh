# Disables WordPress debug mode

function ee_mod_debug_wordpress_stop()
{
	grep "'WP_DEBUG'" /var/www/$EE_DOMAIN/wp-config.php | grep true &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		# Stop debug WordPress
		ee_lib_echo "Stopping WordPress debug logs for $EE_DOMAIN"
		sed -i "s/define('WP_DEBUG', true);/define('WP_DEBUG', false);/" /var/www/$EE_DOMAIN/wp-config.php
		sed -i "/define('WP_DEBUG_DISPLAY', false);/d" /var/www/$EE_DOMAIN/wp-config.php
		sed -i "/define('WP_DEBUG_LOG', true);/d" /var/www/$EE_DOMAIN/wp-config.php
		sed -i "/define('SAVEQUERIES', true);/d" /var/www/$EE_DOMAIN/wp-config.php
	else
		ee_lib_echo "WordPress debug log already stopped for $EE_DOMAIN"
	fi
}
