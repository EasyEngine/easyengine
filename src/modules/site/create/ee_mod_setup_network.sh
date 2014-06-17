# Setup WordPress Network

function ee_mod_setup_network()
{
	cd /var/www/$EE_DOMAIN/htdocs || ee_lib_error "Unable To Change Directory"
	wp core install-network --allow-root --title="$EE_WWW_DOMAIN" $EE_WP_SUBDOMAIN &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to setup WordPress Network, exit status = " $?

	sed -i "/WP_DEBUG/a \define('WP_ALLOW_MULTISITE', true);" /var/www/$EE_DOMAIN/wp-config.php
	sed -i "/WP_ALLOW_MULTISITE/a \define('WPMU_ACCEL_REDIRECT', true);" /var/www/$EE_DOMAIN/wp-config.php

}
