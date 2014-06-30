# NGINX rewrite debug

function ee_mod_debug_rewrite()
{
	if [ "$EE_DEBUG" = "--start" ]; then
		if [ -z $EE_DOMAIN ]; then
			grep "rewrite_log on;" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
			if [ $? -ne 0 ]; then
				ee_lib_echo "Setup NGINX rewrite logs, please wait..."
				sed -i '/http {/a \\trewrite_log on;' /etc/nginx/nginx.conf \
				|| ee_lib_error "Unable to setup NGINX rewrite logs, exit status = " $?

				# NGINX reload trigger
				EE_TRIGGER_NGINX="true"
			else
				# Display message
				ee_lib_echo "NGINX rewrite logs already enabled"
			fi
		else
			grep "rewrite_log on;" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
			if [ $? -ne 0 ]; then
				ee_lib_echo "Setup NGINX rewrite logs for $EE_DOMAIN, please wait..."
				sed -i "/access_log/i \\\trewrite_log on;" /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to setup NGINX rewrite logs for $EE_DOMAIN, exit status = " $?

				# NGINX reload trigger
				EE_TRIGGER_NGINX="true"
			else
				# Display message
				ee_lib_echo "NGINX rewrite logs for $EE_DOMAIN already enabled"
			fi
		fi
	elif [ "$EE_DEBUG" = "--stop" ]; then
		if [ -z $EE_DOMAIN ]; then
			grep "rewrite_log on;" /etc/nginx/nginx.conf &>> $EE_COMMAND_LOG
			if [ $? -eq 0 ]; then
				ee_lib_echo "Disable NGINX rewrite logs, please wait..."sed -i "/rewrite_log.*/d" /etc/nginx/nginx.conf
				sed -i "/rewrite_log.*/d" /etc/nginx/nginx.conf \
				|| ee_lib_error "Unable to disable NGINX rewrite logs, exit status = " $?

				# NGINX reload trigger
				EE_TRIGGER_NGINX="true"
			else
				# Display message
				ee_lib_echo "NGINX rewrite logs already disable"
			fi
		else
			grep "rewrite_log on;" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
			if [ $? -eq 0 ]; then
				ee_lib_echo "Disable NGINX rewrite logs for $EE_DOMAIN, please wait..."
				sed -i "/rewrite_log.*/d" /etc/nginx/sites-available/$EE_DOMAIN \
				|| ee_lib_error "Unable to disable NGINX rewrite logs for $EE_DOMAIN, exit status = " $?

				# NGINX reload trigger
				EE_TRIGGER_NGINX="true"
			else
				# Display message
				ee_lib_echo "NGINX rewrite logs for $EE_DOMAIN already disable"
			fi
		fi
	fi
}
