# Auto switch site options

function ee_mod_site_option()
{
	if [ "$EE_SITE_CREATE_OPTION" = "--basic" ] || [ "$EE_SITE_CREATE_OPTION" = "--w3tc" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsc" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpfc" ]; then
		if [ "$EE_SITE_CACHE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpsubdirectory" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpsubdom" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpsubdomain" ]; then
			EE_SITE_CREATE_OPTION=$EE_FIFTH
			EE_SITE_CACHE_OPTION=$EE_FOURTH
		else
			EE_SITE_CREATE_OPTION=--wp
			EE_SITE_CACHE_OPTION=$EE_FOURTH
		fi
	fi

	# WordPresss subdirectory variables
	if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdirectory" ]; then
		EE_SITE_CREATE_OPTION="--wpsubdir"
		EE_NETWORK_ACTIVATE="--network"
	fi
		
	# WordPress sub-domain variables
	if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdom" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
		EE_SITE_CREATE_OPTION="--wpsubdomain"
		EE_NETWORK_ACTIVATE="--network"
		EE_WP_SUBDOMAIN="--subdomains"
	fi
		
	# Use default whenever possible
	if [ "$EE_SITE_CREATE_OPTION" = "" ]; then
		EE_SITE_CREATE_OPTION=--html
	fi

	# For WordPress sites if $EE_SITE_CACHE_OPTION is empty then used --basic as a $EE_SITE_CACHE_OPTION
	if [ "$EE_SITE_CACHE_OPTION" = "" ] && [ "$EE_SITE_CREATE_OPTION" != "--html" ] && [ "$EE_SITE_CREATE_OPTION" != "--php" ] && [ "$EE_SITE_CREATE_OPTION" != "--mysql" ]; then
		EE_SITE_CACHE_OPTION=--basic
	fi

	# Kick out for invalid cache option
	if [[ "$EE_SECOND" = "update" && "$EE_SITE_CREATE_OPTION" = "--password" ]]; then
		ee_lib_echo "This option is needed for updating WordPress website password" &> /dev/null
	elif [[ "$EE_SITE_CREATE_OPTION" != "--html" && "$EE_SITE_CREATE_OPTION" != "--php" && "$EE_SITE_CREATE_OPTION" != "--mysql" && "$EE_SITE_CREATE_OPTION" != "--wp" && "$EE_SITE_CREATE_OPTION" != "--wpsubdir" && "$EE_SITE_CREATE_OPTION" != "--wpsubdomain" ]]; then
		ee_lib_error "Invalid website type $EE_SITE_CREATE_OPTION, exit status = " $?
	elif [[ "$EE_SITE_CREATE_OPTION" = "--html" || "$EE_SITE_CREATE_OPTION" = "--php" || "$EE_SITE_CREATE_OPTION" = "--mysql" ]] && [ -n "$EE_SITE_CACHE_OPTION" ]; then
		ee_lib_error "Invalid cache option $EE_SITE_CACHE_OPTION for $EE_SITE_CREATE_OPTION website, exit status = " $?
	elif [[ "$EE_SITE_CREATE_OPTION" = "--wp" || "$EE_SITE_CREATE_OPTION" = "--wpsubdir" || "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]] && [[ "$EE_SITE_CACHE_OPTION" != "--basic" && "$EE_SITE_CACHE_OPTION" != "--wpsc" && "$EE_SITE_CACHE_OPTION" != "--w3tc" && "$EE_SITE_CACHE_OPTION" != "--wpfc" ]]; then
		ee_lib_error "Invalid cache option $EE_SITE_CACHE_OPTION, exit status = " $?
	fi
}
