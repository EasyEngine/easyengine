# PHP debug

function ee_mod_debug_php()
{
	if [ "$1" = "start" ]; then
		grep -B2 9001 /etc/nginx/conf.d/upstream.conf | grep php &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			ee_lib_echo "Setup PHP5-FPM slow log, please wait..."
			sed -i "5 s/9000/9001/" /etc/nginx/conf.d/upstream.conf \
			|| ee_lib_error "Unable to setup PHP5-FPM slow log, exit status = " $?

			# NGINX reload trigger
			EE_TRIGGER_NGINX="true"
		else
			# Display message
			ee_lib_echo "PHP5-FPM slow log already enabled"
		fi
	elif [ "$1" = "stop" ]; then
		grep -B2 9001 /etc/nginx/conf.d/upstream.conf | grep php &>> $EE_COMMAND_LOG
		if [ $? -eq 0 ]; then
			ee_lib_echo "Disable PHP5-FPM slow log, please wait..."
			sed -i "5 s/9001/9000/" /etc/nginx/conf.d/upstream.conf \
			|| ee_lib_error "Unable to disable PHP5-FPM slow log, exit status = " $?

			# NGINX reload trigger
			EE_TRIGGER_NGINX="true"
		else
			# Display message
			ee_lib_echo "PHP5-FPM slow log already disabled"
		fi
	fi
}