# Update Websites

function ee_mod_update_website() {
	# Let's take backup first
	ee_mod_site_backup

	# Install required packages
	ee_mod_site_packages

	# Let's start update
	ee_mod_update_nginx

	# Setup MySQL database for HTML|PHP websites
	if [ "$EE_SITE_CREATE_OPTION" = "--mysql" ]; then
		ee_mod_setup_database

		# Add Database Information On ee-config.php
		echo -e "define('DB_NAME', '$EE_DB_NAME'); \ndefine('DB_USER', '$EE_DB_USER'); \ndefine('DB_PASSWORD', '$EE_DB_PASS'); \ndefine('DB_HOST', '$EE_MYSQL_HOST');" \
		&>> /var/www/$EE_DOMAIN/ee-config.php
	fi

	# Use same database when update MySQL website updated to WordPress
	if [ "$EE_SITE_CURRENT_TYPE" = "--mysql" ]; then
		# Use same database for WordPress
		EE_DB_NAME=$(grep DB_NAME $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed "s'htdocs'backup/$EE_DATE/ee-config.php'" 2> /dev/null) | cut -d"'" -f4)
		EE_DB_USER=$(grep DB_USER $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed "s'htdocs'backup/$EE_DATE/ee-config.php'" 2> /dev/null) | cut -d"'" -f4)
		EE_DB_PASS=$(grep DB_PASSWORD $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g' | sed "s'htdocs'backup/$EE_DATE/ee-config.php'" 2> /dev/null) | cut -d"'" -f4)
	fi

	# Setup/Install WordPress for HTML|PHP|MySQL websites
	if [[ "$EE_SITE_CURRENT_TYPE" = "--html" || "$EE_SITE_CURRENT_TYPE" = "--php" || "$EE_SITE_CURRENT_TYPE" = "--mysql"  ]] &&
		 [[ "$EE_SITE_CREATE_OPTION" = "--wp" || "$EE_SITE_CREATE_OPTION" = "--wpsubdir" || "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]]; then
		# Setup WordPress
		ee_mod_setup_wordpress

		# Display WordPress credential
		echo
		ee_lib_echo_info "WordPress Admin Username: $EE_WP_USER"
		ee_lib_echo_info "WordPress Admin Password: $EE_WP_PASS"
		echo

		# Display WordPress cache plugin settings
		ee_mod_plugin_settings

	# Update WordPress Websites
	elif [[ "$EE_SITE_CURRENT_WP" = "--wp" || "$EE_SITE_CURRENT_WP" = "--wpsubdir" || "$EE_SITE_CURRENT_WP" = "--wpsubdomain" ]]; then
		# Setup WordPress Network for --wp websites
		if [[ "$EE_SITE_CURRENT_WP" = "--wp" ]]; then
			if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
				ee_mod_setup_network
			fi
		fi

		# Uninstall unwanted plugins
		# Current site type: --wp --wpsc
		# Update site type: --wpsubdomain --wpsc
		# Only delete plugin when current cache is --wpsc and update cache is not --wpsc
		if [[ "$EE_SITE_CURRENT_CACHE" = "--wpsc" && "$EE_SITE_CACHE_OPTION" != "--wpsc" ]]; then
			ee_lib_echo "Unistalling WP Super Cache plugin, please wait..."
			wp plugin --allow-root uninstall wp-super-cache &>> $EE_COMMAND_LOG
		fi

		# Delete plugin when current cache is --w3tc|--wpfc and update cache is not --w3tc|--wpfc
		if [[ "$EE_SITE_CURRENT_CACHE" = "--w3tc" || "$EE_SITE_CURRENT_CACHE" = "--wpfc"  ]] && [[ "$EE_SITE_CACHE_OPTION" != "--w3tc" && "$EE_SITE_CACHE_OPTION" != "--wpfc"  ]]; then
			ee_lib_echo "Uninstalling W3 Total Cache plugin, please wait..."
			wp plugin --allow-root uninstall w3-total-cache &>> $EE_COMMAND_LOG 
		fi

		# Install WordPress plugins
		# As nginx-helper is installed all type of WordPress
		# We don't need to install it again
		#ee_mod_plugin_nginx_helper

		if [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
			ee_mod_plugin_wpsc
		fi

		if [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
			ee_mod_plugin_w3tc
		fi

		# Display WordPress cache plugin settings
		ee_mod_plugin_settings
	fi

	# Use this variable to detect and change ownership, reload nginx, 
	EE_UPDATE_WEBSITE="success"
}