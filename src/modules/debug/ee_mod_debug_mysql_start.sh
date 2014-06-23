# Enables MySQL debug mode

function ee_mod_debug_mysql_start()
{
	# Check MySql slow logs is on
	#grep slow-query-log /etc/mysql/my.cnf &>> $EE_COMMAND_LOG
	mysql -e "show variables like 'slow_query_log';" | grep ON &>> $EE_COMMAND_LOG

	if [ $? -ne 0 ]; then
		# Enable MySQL slow logs
		ee_lib_echo "Setting up MySQL slow log, please wait..."
		mysql -u -e "set global slow_query_log = 'ON';"
		mysql -u -e "set global slow_query_log_file = '/var/log/mysql/slow.log';"
		mysql -u -e "set global long_query_time=2;"
		mysql -u -e "set global log_queries_not_using_indexes = 'ON';"
		#sed -i "/#long_query_time/i slow-query-log = 1\nslow-query-log-file = /var/log/mysql/slow.log" /etc/mysql/my.cnf
		#sed -i "s/#long_query_time/long_query_time/" /etc/mysql/my.cnf
		#sed -i "s/#log-queries-not-using-indexes/log-queries-not-using-indexes/" /etc/mysql/my.cnf
	else
		# Lets disable MySQL restart trigger
		#EE_DEBUG_MYSQL=""
		ee_lib_echo "MySQL slow log already enabled"
	fi
}
