# Update NGINX configuration for $EE_DOMAIN

function ee_mod_update_nginx()
{
	# Git commit
	ee_lib_git /etc/nginx/ "Before ee site update: $EE_DOMAIN running on $EE_SITE_CURRENT_TYPE"
		
	# Update NGINX configuration
	ee_lib_echo "Updating $EE_DOMAIN, please wait..."
	
	if [ -f /etc/nginx/sites-available/$EE_DOMAIN ]; then
		# Find out current NGINX configuration header
		ee_nginx_current_header=$(head -n1 /etc/nginx/sites-available/$EE_DOMAIN | grep "NGINX CONFIGURATION")
		
		# Update NGINX configuration header
		if [ "$EE_SITE_CREATE_OPTION" = "--html" ] || [ "$EE_SITE_CREATE_OPTION" = "--php" ] || [ "$EE_SITE_CREATE_OPTION" = "--mysql" ]; then
			ee_nginx_conf=$(echo $EE_SITE_CREATE_OPTION | cut -c3-)/basic.conf
		elif [ "$EE_SITE_CACHE_OPTION" = "--basic" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ] || [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ] || [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
			ee_nginx_conf=$(echo $EE_SITE_CREATE_OPTION | cut -c3-)/$(echo $EE_SITE_CACHE_OPTION | cut -c3-).conf
		fi
		ee_nginx_update_header=$(head -n1 /usr/share/easyengine/nginx/$ee_nginx_conf | grep "NGINX CONFIGURATION")
		
		# Echo values
		echo -e "EE_DOMAIN_CHECK = $EE_DOMAIN_CHECK \nEE_SITE_CREATE_OPTION = $EE_SITE_CREATE_OPTION \nEE_SITE_CACHE_OPTION = $EE_SITE_CACHE_OPTION \nEE_NETWORK_ACTIVATE = $EE_NETWORK_ACTIVATE \nEE_WP_SUBDOMAIN = $EE_WP_SUBDOMAIN \nee_nginx_update_header = $ee_nginx_update_header" &>> $EE_COMMAND_LOG

		# Update Head Line of NGINX conf
		sed -i "s'$ee_nginx_current_header'$ee_nginx_update_header'" /etc/nginx/sites-available/$EE_DOMAIN || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?

		# Update NGINX conf for HTML site
		if [ "$EE_SITE_CURRENT_TYPE" = "--html" ]; then
				sed -i 's/access\.log/access.log rt_cache/' /etc/nginx/sites-available/$EE_DOMAIN && \
				sed -i '/index index.html index.htm;$/c \\tindex index.php index.htm index.html;' /etc/nginx/sites-available/$EE_DOMAIN && \
				sed -i '/location \/ {/,/}/d ' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				
				# Update HTML to PHP MySQL --basic (--wp/--wpsubdir/--wpsubdomain) options
				if [ "$EE_SITE_CREATE_OPTION" = "--php" ] || [ "$EE_SITE_CREATE_OPTION" = "--mysql" ] || [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/php.conf;' /etc/nginx/sites-available/$EE_DOMAIN \
					|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				# Update HTML to --wpsc (--wp/--wpsubdir/--wpsubdomain) options
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/wpsc.conf;' /etc/nginx/sites-available/$EE_DOMAIN \
					|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				# Update HTML to --w3tc (--wp/--wpsubdir/--wpsubdomain) options
				elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/w3tc.conf;' /etc/nginx/sites-available/$EE_DOMAIN \
					|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				# Update HTML to --wpfc (--wp/--wpsubdir/--wpsubdomain) options
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/wpfc.conf;' /etc/nginx/sites-available/$EE_DOMAIN \
					|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				fi

		# Update PHP MySQL --basic (--wp/--wpsubdir/--wpsubdomain) to --wpsc --w3tc --wpfc options
		elif [ "$EE_SITE_CURRENT_TYPE" = "--php" ] || [ "$EE_SITE_CURRENT_TYPE" = "--mysql" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wp --basic" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdir --basic" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdomain --basic" ]; then
				if [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
					sed -i 's/include common\/php.conf/include common\/wpsc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
					|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
					sed -i 's/include common\/php.conf/include common\/w3tc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
					|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
					sed -i 's/include common\/php.conf/include common\/wpfc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
					|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
				fi

		# Update --wpsc (--wp/--wpsubdir/--wpsubdomain) to --basic --w3tc --wpfc options
		elif [ "$EE_SITE_CURRENT_TYPE" = "--wp --wpsc" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdir --wpsc" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdomain --wpsc" ]; then
			if [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
				sed -i 's/include common\/wpsc.conf/include common\/php.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
				sed -i 's/include common\/wpfc.conf/include common\/w3tc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			elif [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
				sed -i 's/include common\/wpsc.conf/include common\/wpfc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			fi

		# Update --w3tc (--wp/--wpsubdir/--wpsubdomain) to --basic --wpsc --wpfc options
		elif [ "$EE_SITE_CURRENT_TYPE" = "--wp --w3tc" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdir --w3tc" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdomain --w3tc" ]; then
			if [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
				sed -i 's/include common\/w3tc.conf/include common\/php.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			elif [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
				sed -i 's/include common\/w3tc.conf/include common\/wpsc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			elif [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
				sed -i 's/include common\/w3tc.conf/include common\/wpfc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			fi

		# Update --wpfc (--wp/--wpsubdir/--wpsubdomain) to --basic --wpsc --w3tc options
		elif [ "$EE_SITE_CURRENT_TYPE" = "--wp --wpfc" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdir --wpfc" ] || [ "$EE_SITE_CURRENT_TYPE" = "--wpsubdomain --wpfc" ]; then
			if [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
				sed -i 's/include common\/wpfc.conf/include common\/php.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			elif [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
				sed -i 's/include common\/wpfc.conf/include common\/wpsc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
				sed -i 's/include common\/wpfc.conf/include common\/w3tc.conf/' /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to update NGINX configuration to $EE_SITE_CREATE_OPTION $EE_SITE_CACHE_OPTION, exit status =" $?
			fi
		fi

		# Add WordPress common file wpcommon.conf for HTML PHP & MYSQL sites
		if [[ "$EE_SITE_CURRENT_TYPE" = "--html" || "$EE_SITE_CURRENT_TYPE" = "--php" || "$EE_SITE_CURRENT_TYPE" = "--mysql" ]] && \
			[[ "$EE_SITE_CREATE_OPTION" = "--wp" || "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" || "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ]]; then	
			sed -i '/include common\/locations.conf/i \\tinclude common\/wpcommon.conf;' /etc/nginx/sites-available/$EE_DOMAIN || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
		fi

		# Update server_name for HTML PHP MYSQL WP (single site) only
		# Don't execute for WordPress Multisite
		if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ] \
			&& [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdir --basic" ] && [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdomain --basic" ] \
			&& [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdir --wpsc" ] && [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdomain --wpsc" ] \
			&& [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdir --w3tc" ] && [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdomain --w3tc" ] \
			&& [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdir --wpfc" ] && [ "$EE_SITE_CURRENT_TYPE" != "--wpsubdomain --wpfc" ]; then
		
			sed -i "s'server_name $EE_DOMAIN www.$EE_DOMAIN;'server_name $EE_DOMAIN *.$EE_DOMAIN;'" /etc/nginx/sites-available/$EE_DOMAIN && \
			sed -i '/server_name.*;/i \\t# Uncomment the following line for domain mapping;\n\t# listen 80 default_server;\n' /etc/nginx/sites-available/$EE_DOMAIN && \
			sed -i '/server_name.*;/a \\n\t# Uncomment the following line for domain mapping \n\t#server_name_in_redirect off;' /etc/nginx/sites-available/$EE_DOMAIN && \
			sed -i '/include common\/locations.conf/i \\tinclude common\/wpsubdir.conf;' /etc/nginx/sites-available/$EE_DOMAIN || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
		fi
	else
		ee_lib_error "Unable to find $EE_DOMAIN NGINX configuration, exit status =" $?
	fi
}
