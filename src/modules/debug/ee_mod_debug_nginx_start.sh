# Enables NGINX debug mode

function ee_mod_debug_nginx_start()
{
	# Lets disable NGINX reload trigger
	EE_DEBUG_NGINX=""

	if [ -z "$EE_IP_ADDRESS" ]; then
		#read -p "Enter the single IP address for debugging: " EE_IP_ADDRESS
		EE_IP_ADDRESS="0.0.0.0/0"
	fi

	for ee_debug_ip_address in $(echo $EE_IP_ADDRESS); do
		grep "debug_connection $ee_debug_ip_address" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			# Enable debug connection
			ee_lib_echo "Setting up NGINX debug connection, please wait..."

			# EasyEngine found new IP address which is not present in nginx.conf
			sed -i "/events {/a \\\t$(echo debug_connection $ee_debug_ip_address\;)" /etc/nginx/nginx.conf

			# Lets trigger the NGINX reload
			EE_DEBUG_NGINX="--nginx"
    fi
	done

	if [ -z "$EE_DEBUG_NGINX" ]; then
		ee_lib_echo "NGINX debug connection already enabled"
	fi
}
