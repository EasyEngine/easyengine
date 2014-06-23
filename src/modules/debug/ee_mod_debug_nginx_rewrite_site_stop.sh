# Disables NGINX rewrite_log mode for $EE_DOMAIN

function ee_mod_debug_nginx_rewrite_site_stop()
{
	grep "rewrite_log on;" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		# Disable NGINX rewrite logs
		ee_lib_echo "Stopping up NGINX rewrite logs for $EE_DOMAIN"
		sed -i "/rewrite_log.*/d" /etc/nginx/sites-available/$EE_DOMAIN
	else
		# Lets disable NGINX reload trigger
		EE_DEBUG_REWRITE=""
		ee_lib_echo "Rewrites logs already stop for $EE_DOMAIN"
	fi
}
