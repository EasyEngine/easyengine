# Disables PHP5-FPM debug mode

function ee_mod_debug_php_fpm_stop()
{
	grep "log_level = notice" /etc/php5/fpm/php-fpm.conf &>> $EE_COMMAND_LOG
	if [ $? -ne 0 ]; then
		# Disable PHP5-FPM error logs in debug mode
		ee_lib_echo "Stopping PHP5-FPM log level in debug mode, please wait..."
		sed -i "s/log_level = debug/log_level = notice/" /etc/php5/fpm/php-fpm.conf
	else
		EE_DEBUG_FPM=""
		ee_lib_echo "PHP5-FPM log level already in notice (default) mode"
	fi
}
