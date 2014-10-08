# Update cache plugins

function ee_mod_update_plugins()
{
	cd /var/www/$EE_DOMAIN/htdocs/

	if [ "$EE_SITE_CURRENT_OPTION" = "WPSINGLE W3 TOTAL CACHE" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSINGLE FAST CGI" ] \
		|| [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDIR W3 TOTAL CACHE" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDIR FAST CGI" ] \
		|| [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDOMAIN W3 TOTAL CACHE" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDOMAIN FAST CGI" ] && [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
		ee_lib_echo "Uninstalling W3 Total Cache plugin, please wait..."
		wp plugin --allow-root uninstall w3-total-cache &>> $EE_COMMAND_LOG 
	fi

	if [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDOMAIN WP SUPER CACHE" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSINGLE WP SUPER CACHE" ] \
		|| [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDIR WP SUPER CACHE" ] && [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
		ee_lib_echo "Unnstalling WP Super Cache plugin, please wait..."
		wp plugin --allow-root uninstall wp-super-cache &>> $EE_COMMAND_LOG
	fi

	if [ "$EE_SITE_CURRENT_OPTION" != "WPSINGLE WP SUPER CACHE" ] && [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDIR WP SUPER CACHE" ] \
		&& [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDOMAIN WP SUPER CACHE" ] && [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
		ee_mod_plugin_wpsc
	fi

	if [ "$EE_SITE_CURRENT_OPTION" != "WPSINGLE W3 TOTAL CACHE" ] && [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDIR W3 TOTAL CACHE" ] \
		|| [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDOMAIN W3 TOTAL CACHE" ] && [ "$EE_SITE_CURRENT_OPTION" != "WPSINGLE FAST CGI" ] || [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDIR FAST CGI" ] \
		|| [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDOMAIN FAST CGI" ] && [[ "$EE_SITE_CACHE_OPTION" = "--wpfc" || "$EE_SITE_CACHE_OPTION" = "--w3tc" ]]; then
		ee_mod_plugin_w3tc
	fi
}
