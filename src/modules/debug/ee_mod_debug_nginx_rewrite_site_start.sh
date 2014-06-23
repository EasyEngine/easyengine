# Enables NGINX rewrite_log mode for $EE_DOMAIN

function ee_mod_nginx_rewrite_site_start()
{
	grep "rewrite_log on;" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
	if [ $? -ne 0 ]; then
		# Enable NGINX rewrite logs
		ee_lib_echo "Setting up NGINX rewrite logs for $EE_DOMAIN"
		sed -i "/access_log/i \\\trewrite_log on;" /etc/nginx/sites-available/$EE_DOMAIN
	else
		# Lets disable NGINX reload trigger
		EE_DEBUG_REWRITE=""
		ee_lib_echo "Rewrites logs already on for $EE_DOMAIN"
	fi
}
