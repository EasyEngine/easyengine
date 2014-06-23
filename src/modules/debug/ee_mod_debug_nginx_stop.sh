# Disables NGINX debug mode

function ee_mod_debug_nginx_stop()
{
	grep "debug_connection" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		ee_lib_echo "Stopping NGINX debug connection, please wait..."
		sed -i "/debug_connection.*/d" /etc/nginx/nginx.conf
	else
		# Lets disable NGINX reload trigger
		EE_DEBUG_NGINX=""
		ee_lib_echo "NGINX debug connection already stopped"
	fi
}
