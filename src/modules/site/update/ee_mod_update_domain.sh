# Update NGINX configuration for $EE_DOMAIN

function ee_mod_update_domain()
{
		# Git commit
		ee_lib_git /etc/nginx/ "Before ee site update: $EE_DOMAIN running on $EE_SITE_CURRENT_OPTION"
		# Backup NGINX configuration & Database & Webroot
		ee_mod_site_backup
		
		# Creating $EE_DOMAIN
		ee_lib_echo "Updating $EE_DOMAIN, please wait ..."
		if [ -f /etc/nginx/sites-available/$EE_DOMAIN ]; then 
			EE_SITE_CURRENT_CONF=$(head -n1 /etc/nginx/sites-available/$EE_DOMAIN | grep "NGINX CONFIGURATION")
			EE_SITE_UPDATE_CONF=$(head -n1 /usr/share/easyengine/nginx/$EE_NGINX_CONF | grep "NGINX CONFIGURATION")
			EE_SITE_NGINX_CONF="/etc/nginx/sites-available/$EE_DOMAIN"

			# Update Head Line of NGINX conf
			sed -i "s'$EE_SITE_CURRENT_CONF'$EE_SITE_UPDATE_CONF'" $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?

			# Update Head Line of NGINX conf file
			if [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ] \
				&& [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDOMAIN BASIC" ] && [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDIR BASIC" ] \
				&& [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDOMAIN WP SUPER CACHE" ] && [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDIR WP SUPER CACHE"] \
				&& [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDOMAIN FAST CGI" ] && [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDIR FAST CGI"] \
				&& [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDOMAIN W3 TOTAL CACHE" ] && [ "$EE_SITE_CURRENT_OPTION" != "WPSUBDIR W3 TOTAL CACHE"];then
				sed -i "s'server_name $EE_DOMAIN www.$EE_DOMAIN;'server_name $EE_DOMAIN *.$EE_DOMAIN;'" $EE_SITE_NGINX_CONF && \
				sed -i '/server_name.*;/i \\t# Uncomment the following line for domain mapping;\n\t# listen 80 default_server;\n' $EE_SITE_NGINX_CONF && \
				sed -i '/server_name.*;/a \\n\t# Uncomment the following line for domain mapping \n\t#server_name_in_redirect off;' $EE_SITE_NGINX_CONF && \
				sed -i '/include common\/locations.conf/i \\tinclude common\/wpsubdir.conf;' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
			fi
			# Update NGINX conf for HTML site
			if [ "$EE_SITE_CURRENT_OPTION" = "HTML" ]; then
				sed -i 's/access\.log/access.log rt_cache/' $EE_SITE_NGINX_CONF && \
				sed -i '/location \/ {/,/}/c \\tindex index.php index.htm index.html' $EE_SITE_NGINX_CONF  || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				if [ "$EE_SITE_CACHE_OPTION" = "--basic" ] || [[ "$EE_SITE_CREATE_OPTION" = "--php" || "$EE_SITE_CREATE_OPTION" = "--mysql" ]]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/php.conf;' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/wpfc.conf;' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/wpsc.conf;' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
					sed -i '/include common\/locations.conf/i \\tinclude common\/w3tc.conf;' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				fi
			# Update NGINX conf from BASIC CACHE to WPFC|W3TC|WPSC CACHE  
			elif [ "$EE_SITE_CURRENT_OPTION" = "PHP" ] || [ "$EE_SITE_CURRENT_OPTION" = "MYSQL" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSINGLE BASIC" ] \
				|| [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDIR BASIC" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDOMAIN BASIC" ]; then
				if [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
					sed -i 's/include common\/php.conf/include common\/wpfc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
					sed -i 's/include common\/php.conf/include common\/wpsc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
					sed -i 's/include common\/php.conf/include common\/w3tc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				fi
			# Update NGINX conf from W3TC CACHE to BASIC|WPSC|WPFC CACHE  
			elif [ "$EE_SITE_CURRENT_OPTION" = "WPSINGLE W3 TOTAL CACHE" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDIR W3 TOTAL CACHE" ] \
				|| [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDOMAIN W3 TOTAL CACHE" ]; then
				if [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
					sed -i 's/include common\/w3tc.conf/include common\/wpfc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
					sed -i 's/include common\/w3tc.conf/include common\/wpsc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
					sed -i 's/include common\/w3tc.conf/include common\/php.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				fi	
			# Update NGINX conf from WPFC CACHE to BASIC|W3TC|WPSC CACHE 
			elif [ "$EE_SITE_CURRENT_OPTION" = "WPSINGLE FAST CGI" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDIR FAST CGI" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDOMAIN FAST CGI" ]; then
				if [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
					sed -i 's/include common\/wpfc.conf/include common\/php.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpsc" ]; then
					sed -i 's/include common\/wpfc.conf/include common\/wpsc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
					sed -i 's/include common\/wpfc.conf/include common\/w3tc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				fi
			# Update NGINX conf from WPSC CACHE to BASIC|W3TC|WPFC CACHE 
			elif [ "$EE_SITE_CURRENT_OPTION" = "WPSINGLE WP SUPER CACHE" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDIR WP SUPER CACHE" ] || [ "$EE_SITE_CURRENT_OPTION" = "WPSUBDOMAIN WP SUPER CACHE" ]; then
				if [ "$EE_SITE_CACHE_OPTION" = "--basic" ]; then
					sed -i 's/include common\/wpsc.conf/include common\/php.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--wpfc" ]; then
					sed -i 's/include common\/wpsc.conf/include common\/wpfc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				elif [ "$EE_SITE_CACHE_OPTION" = "--w3tc" ]; then
					sed -i 's/include common\/wpfc.conf/include common\/w3tc.conf/' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
				fi
			fi
			
			# Update NGINX conf from HTML|PHP|MYSQL to wp|wpsubdir|wpsubdomain
			if [[ "$EE_SITE_CREATE_OPTION" = "--wp" || "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" || "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ]] \
				&& [[ "$EE_SITE_CURRENT_OPTION" = "HTML" || "$EE_SITE_CURRENT_OPTION" = "PHP" || "$EE_SITE_CURRENT_OPTION" = "MYSQL" ]]; then	
				sed -i '/include common\/locations.conf/i \\tinclude common\/wpcommon.conf;' $EE_SITE_NGINX_CONF || ee_lib_error "Unable to update nginx configuration to $EE_SITE_CREATE_OPTION, $EE_SITE_CACHE_OPTION for $EE_DOMAIN, exit status =" $?
			fi 
		else
			ee_lib_error "Unable to find $EE_DOMAIN NGINX configuration, exit status =" $?
		fi
}
