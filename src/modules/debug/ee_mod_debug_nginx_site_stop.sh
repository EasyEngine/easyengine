# Disables NGINX site debug mode

function ee_mod_debug_nginx_site_stop()
{
	grep "error.log debug" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		# Disable NGINX debug log
		ee_lib_echo "Disable $EE_DOMAIN error logs in debugging mode,please wait..."
		sed -i "s/error.log debug;/error.log;/" /etc/nginx/sites-available/$EE_DOMAIN
	else
		# Lets disable NGINX reload trigger
		EE_DEBUG_NGINX=""
		ee_lib_echo "Already stopped $EE_DOMAIN error logs in debugging mode"
	fi
}
