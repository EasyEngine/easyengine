# MySQL debug

function ee_mod_debug_mysql()
{
	if [ "$1" = "start" ]; then
		mysql -e "show variables like 'slow_query_log';" | grep ON &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			ee_lib_echo "Setup MySQL slow log, please wait..."

			mysql -e "set global slow_query_log = 'ON';" \
			|| ee_lib_error "Unable to setup slow_query_log, exit status = " $?

			mysql -e "set global slow_query_log_file = '/var/log/mysql/slow.log';" \
			|| ee_lib_error "Unable to setup slow_query_log_file, exit status = " $?

			mysql -e "set global long_query_time = 2;" \
			|| ee_lib_error "Unable to setup long_query_time, exit status = " $?

			mysql -e "set global log_queries_not_using_indexes = 'ON';" \
			|| ee_lib_error "Unable to setup log_queries_not_using_indexes, exit status = " $?
		else
			# Display message
			ee_lib_echo "MySQL slow log already enabled"
		fi
	elif [ "$1" = "stop" ]; then
		mysql -e "show variables like 'slow_query_log';" | grep ON &>> $EE_COMMAND_LOG
		if [ $? -eq 0 ]; then
			ee_lib_echo "Disable MySQL slow log, please wait..."

			mysql -e "set global slow_query_log = 'OFF';" \
			|| ee_lib_error "Unable to setup slow_query_log, exit status = " $?

			mysql -e "set global slow_query_log_file = '/var/log/mysql/slow.log';" \
			|| ee_lib_error "Unable to setup slow_query_log_file, exit status = " $?

			mysql -e "set global long_query_time = 10;" \
			|| ee_lib_error "Unable to setup long_query_time, exit status = " $?

			mysql -e "set global log_queries_not_using_indexes = 'OFF';" \
		else
			# Display message
			ee_lib_echo "MySQL slow log already disable"
		fi
	fi
}
