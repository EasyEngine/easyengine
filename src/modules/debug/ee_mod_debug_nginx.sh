# NGINX debug

function ee_mod_debug_nginx()
{
	if [ "$EE_DEBUG" = "--start" ]; then
		if [ -z $EE_DOMAIN ]; then
			if [ -z "$EE_IP_ADDRESS" ]; then
				# Enable NGINX debug for all IP
				EE_IP_ADDRESS="0.0.0.0/0"
			fi

			for ee_ip in $EE_IP_ADDRESS; do
				grep "debug_connection $ee_ip" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
				if [ $? -ne 0 ]; then
					ee_lib_echo "Setup NGINX debug connection for $ee_ip, please wait..."
					sed -i "/events {/a \\\t$(echo debug_connection $ee_ip\;)" /etc/nginx/nginx.conf \
					|| ee_lib_error "Unable to setup NGINX debug connection for $ee_ip, exit status = " $?

					# NGINX reload trigger
					EE_TRIGGER_NGINX="true"
				fi
			done

			if [ "$EE_TRIGGER_NGINX" != "true" ]; then
				# Display message
				ee_lib_echo "NGINX debug connection already enabled"
			fi
		else
			grep "error.log debug" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
			if [ $? -ne 0 ]; then
				ee_lib_echo "Setup NGINX debug connection for $EE_DOMAIN, please wait..."
				sed -i "s/error.log;/error.log debug;/" /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to setup NGINX debug connection for for $EE_DOMAIN, exit status = " $?

				# NGINX reload trigger
				EE_TRIGGER_NGINX="true"
			else
				# Display message
				ee_lib_echo "Already enable NGINX debug connection for $EE_DOMAIN"
			fi
		fi
	elif [ "$EE_DEBUG" = "--stop" ]; then
		if [ -z $EE_DOMAIN ]; then
			grep "debug_connection" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
			if [ $? -eq 0 ]; then
				ee_lib_echo "Disable NGINX debug connection, please wait..."
				sed -i "/debug_connection.*/d" /etc/nginx/nginx.conf \
				|| ee_lib_error "Unable to disable NGINX debug connection, exit status = " $?
				# NGINX reload trigger
				EE_TRIGGER_NGINX="true"
			else
				# Display message
				ee_lib_echo "NGINX debug connection already disable"
			fi
		else
			grep "error.log debug" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
			if [ $? -eq 0 ]; then
				ee_lib_echo "Disable NGINX debug connection for $EE_DOMAIN, please wait..."
				sed -i "s/error.log debug;/error.log;/" /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to disable NGINX debug connection for $EE_DOMAIN, exit status = " $?
				# NGINX reload trigger
				EE_TRIGGER_NGINX="true"
			else
				# Display message
				ee_lib_echo "Already disable NGINX debug connection for $EE_DOMAIN"
			fi
		fi
	fi
}
