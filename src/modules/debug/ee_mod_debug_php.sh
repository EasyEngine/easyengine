# PHP debug

function ee_mod_debug_php()
{
	if [ "$EE_DEBUG" = "--start" ]; then
		# Perform search inside upstream php block
		sed -n "/upstream php {/,/}/p" /etc/nginx/conf.d/upstream.conf | grep 9001 &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			ee_lib_echo "Setup PHP5-FPM slow log, please wait..."
			sed -i "/upstream php {/,/}/s/9000/9001/" /etc/nginx/conf.d/upstream.conf \
			|| ee_lib_error "Unable to setup PHP5-FPM slow log, exit status = " $?

			# NGINX reload trigger
			EE_TRIGGER_NGINX="true"
		else
			# Display message
			ee_lib_echo "PHP5-FPM slow log already enabled"
		fi
	elif [ "$EE_DEBUG" = "--stop" ]; then
		# Perform search inside upstream php block
		sed -n "/upstream php {/,/}/p" /etc/nginx/conf.d/upstream.conf | grep 9001 &>> $EE_COMMAND_LOG
		if [ $? -eq 0 ]; then
			ee_lib_echo "Disable PHP5-FPM slow log, please wait..."
			sed -i "/upstream php {/,/}/s/9001/9000/" /etc/nginx/conf.d/upstream.conf  \
			|| ee_lib_error "Unable to disable PHP5-FPM slow log, exit status = " $?

			# NGINX reload trigger
			EE_TRIGGER_NGINX="true"
		else
			# Display message
			ee_lib_echo "PHP5-FPM slow log already disabled"
		fi
	fi
}
