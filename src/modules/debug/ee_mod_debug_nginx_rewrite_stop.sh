# Disables NGINX rewrite_log mode

function ee_mod_debug_nginx_rewrite_stop()
{
	grep "rewrite_log on;" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		# Disable NGINX rewrite logs
		ee_lib_echo "Stopping NGINX Rewrite Logs, Please Wait..."
		sed -i "/rewrite_log.*/d" /etc/nginx/nginx.conf
	else
		# Lets disable NGINX reload trigger
		EE_DEBUG_REWRITE=""
		ee_lib_echo "NGINX rewrites logs already stop"
	fi
}
