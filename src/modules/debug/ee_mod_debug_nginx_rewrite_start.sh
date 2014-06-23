# Enables NGINX rewrite_log mode

function ee_mod_debug_nginx_rewrite_start()
{
	grep "rewrite_log on;" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
	if [ $? -ne 0 ]; then
		# Enable NGINX rewrite logs
		ee_lib_echo "Setting up NGINX rewrite logs, please wait..."
		sed -i '/http {/a \\trewrite_log on;' /etc/nginx/nginx.conf
	else
		# Lets disable NGINX reload trigger
		EE_DEBUG_REWRITE=""
		ee_lib_echo "NGINX rewrites logs already on"
	fi
}
