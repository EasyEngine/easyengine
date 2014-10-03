# Update Domain setup

function ee_mod_update_domain()
{
		# Creating $EE_DOMAIN
		ee_lib_echo "Updating $EE_DOMAIN, please wait..."
				
		sed "s/example.com/$EE_DOMAIN/g" \
		/usr/share/easyengine/nginx/$EE_NGINX_CONF \
		> /etc/nginx/sites-available/$EE_DOMAIN \
		|| ee_lib_error "Unable to update NGINX configuration file for $EE_DOMAIN, exit status = " $?

		# Creating symbolic link
		ee_lib_echo "Creating symbolic link for $EE_DOMAIN"
		if [ ! -e  /etc/nginx/sites-enabled/$EE_DOMAIN ]; then
			ee_lib_symbolic_link /etc/nginx/sites-available/$EE_DOMAIN /etc/nginx/sites-enabled/
		fi
}
