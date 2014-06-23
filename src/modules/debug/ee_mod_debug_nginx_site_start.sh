# Enable NGINX site debug mode

function ee_mod_debug_nginx_site_start()
{
	grep "error.log debug" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
	if [ $? -ne 0 ]; then
		# Enable NGINX debug log
		ee_lib_echo "Setting up $EE_DOMAIN error logs in debugging mode,please wait..."
		sed -i "s/error.log;/error.log debug;/" /etc/nginx/sites-available/$EE_DOMAIN
	else
		# Lets disable NGINX reload trigger
		EE_DEBUG_NGINX=""
		ee_lib_echo "Already started $EE_DOMAIN error logs in debugging mode"
	fi
}
