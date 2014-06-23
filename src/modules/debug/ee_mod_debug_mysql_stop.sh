#  MySQL debug mode

function ee_mod_debug_mysql_stop()
{
	# Check MySql slow logs is off
	#grep slow-query-log /etc/mysql/my.cnf &>> $EE_COMMAND_LOG
	mysql -e "show variables like 'slow_query_log';" | grep ON &>> $EE_COMMAND_LOG
	
	if [ $? -eq 0 ]; then
		# Disable MySQL slow logs
		ee_lib_echo "Stopping MySQL slow log, please wait..."
		mysql -e "set global slow_query_log = 'OFF';"
		mysql -e "set global slow_query_log_file = '/var/log/mysql/slow.log';"
		mysql -e "set global long_query_time=10;"
		mysql -e "set global log_queries_not_using_indexes = 'OFF';"
		#sed -i "/slow-query-log/d" /etc/mysql/my.cnf
		#sed -i "s/long_query_time/#long_query_time/" /etc/mysql/my.cnf
		#sed -i "s/log-queries-not-using-indexes/#log-queries-not-using-indexes/" /etc/mysql/my.cnf
	else
		# Lets disable MySQL restart trigger
		#EE_DEBUG_MYSQL=""
		ee_lib_echo "MySQL slow log already disable"
	fi
}
