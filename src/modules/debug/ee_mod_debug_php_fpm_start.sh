# Enables PHP5-FPM debug mode

function ee_mod_debug_php_fpm_start()
{
	grep "log_level = debug" /etc/php5/fpm/php-fpm.conf &>> $EE_COMMAND_LOG
	if [ $? -ne 0 ]; then
		# Enable PHP5-FPM error logs in debug mode
		ee_lib_echo "Setting up PHP5-FPM log level in debug mode, please wait..."
		sed -i "s';log_level.*'log_level = debug'" /etc/php5/fpm/php-fpm.conf
	else
		EE_DEBUG_FPM=""
		ee_lib_echo "PHP5-FPM log level is already in debug mode"
	fi
}
