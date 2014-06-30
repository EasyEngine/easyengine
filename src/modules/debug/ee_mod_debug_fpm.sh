# PHP5-FPM debug

function ee_mod_debug_fpm()
{
	if [ "$EE_DEBUG" = "--start" ]; then
		grep "log_level = debug" /etc/php5/fpm/php-fpm.conf &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			ee_lib_echo "Setup PHP5-FPM log_level = debug, please wait..."
			sed -i "s';log_level.*'log_level = debug'" /etc/php5/fpm/php-fpm.conf \
			|| ee_lib_error "Unable to setup PHP5-FPM log_level = debug, exit status = " $?

			# PHP5-FPM reload trigger
			EE_TRIGGER_PHP="true"
		else
			# Display message
			ee_lib_echo "PHP5-FPM log_level = debug already setup"
		fi
	elif [ "$EE_DEBUG" = "--stop" ]; then
		grep "log_level = debug" /etc/php5/fpm/php-fpm.conf &>> $EE_COMMAND_LOG
		if [ $? -eq 0 ]; then
			ee_lib_echo "Disable PHP5-FPM log_level = debug, please wait..."
			sed -i "s'log_level.*'log_level = notice'" /etc/php5/fpm/php-fpm.conf \
			|| ee_lib_error "Unable to setup PHP5-FPM log_level = debug, exit status = " $?

			# PHP5-FPM reload trigger
			EE_TRIGGER_PHP="true"
		else
			# Display message
			ee_lib_echo "PHP5-FPM log_level = debug already disabled"
		fi
	fi
}
