# Display WordPress cache plugin settings

function ee_mod_plugin_settings() {
	if [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
		if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
			ee_lib_echo_escape "Configure WPSC:\t\thttp://$EE_DOMAIN/wp-admin/network/settings.php?page=wpsupercache"
		else
			ee_lib_echo_escape "Configure WPSC:\t\thttp://$EE_DOMAIN/wp-admin/options-general.php?page=wpsupercache"
		fi
	fi

	if [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
		if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
			ee_lib_echo_escape "Configure nginx-helper:\thttp://$EE_DOMAIN/wp-admin/network/settings.php?page=nginx"
		else
			ee_lib_echo_escape "Configure nginx-helper:\thttp://$EE_DOMAIN/wp-admin/options-general.php?page=nginx"
		fi
	fi

	if [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
		if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
			ee_lib_echo_escape "Configure W3TC:\t\thttp://$EE_DOMAIN/wp-admin/network/admin.php?page=w3tc_general"
		else
			ee_lib_echo_escape "Configure W3TC:\t\thttp://$EE_DOMAIN/wp-admin/admin.php?page=w3tc_general"
		fi
		if [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
			ee_lib_echo_escape "Page Cache:\t\tDisable"
		elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
			ee_lib_echo_escape "Page Cache:\t\tDisk Enhanced"
		fi
		ee_lib_echo_escape "Database Cache:\t\tMemcached"
		ee_lib_echo_escape "Object Cache:\t\tMemcached"
		ee_lib_echo_escape "Browser Cache:\t\tDisable"
	fi
}
