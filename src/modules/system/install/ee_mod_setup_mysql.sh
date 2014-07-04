# Setup MySQL

function ee_mod_setup_mysql()
{
	ee_lib_echo "Setting up MySQL, please wait..."

	# Setting wait_timeout = 30 & interactive_timeout = 60
	grep "_timeout" /etc/mysql/my.cnf &>> $EE_COMMAND_LOG
	if [ $? -ne 0 ]; then
		sed -i "/#max_connections/a wait_timeout = 30 \ninteractive_timeout = 60" /etc/mysql/my.cnf
		sed -i "/log_error/a slow_query_log_file = /var/log/mysql/slow.log" /etc/mysql/my.cnf
	fi
}
