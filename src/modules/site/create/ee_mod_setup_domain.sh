# Domain setup

function ee_mod_setup_domain()
{
	ls /etc/nginx/sites-available/$EE_DOMAIN &> /dev/null
	if [ $? -ne 0 ]; then

		# Creating $EE_DOMAIN
		ee_lib_echo "Creating $EE_DOMAIN, please wait..."
		sed "s/example.com/$EE_DOMAIN/g" \
		/usr/share/easyengine/nginx/$EE_NGINX_CONF \
		> /etc/nginx/sites-available/$EE_DOMAIN \
		|| ee_lib_error "Unable to create NGINX configuration file for $EE_DOMAIN, exit status = " $?

		# Creating symbolic link
		ee_lib_echo "Creating symbolic link for $EE_DOMAIN"
		ee_lib_symbolic_link /etc/nginx/sites-available/$EE_DOMAIN /etc/nginx/sites-enabled/

		# Creating htdocs & logs directory
		ee_lib_echo "Creating htdocs & logs directory"
		mkdir -p /var/www/$EE_DOMAIN/htdocs && mkdir -p /var/www/$EE_DOMAIN/logs \
		|| ee_lib_error "Unable to create htdocs & logs directory, exit status = " $?

		# Creating symbolic links for logs
		ee_lib_symbolic_link /var/log/nginx/$EE_DOMAIN.access.log /var/www/$EE_DOMAIN/logs/access.log
		ee_lib_symbolic_link /var/log/nginx/$EE_DOMAIN.error.log /var/www/$EE_DOMAIN/logs/error.log
	else
		ee_lib_error "$EE_DOMAIN already exist, exit status = " $?
	fi
}
