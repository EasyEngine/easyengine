# MySQL information

function ee_lib_mysql_info()
{
	local ee_mysql_version=$(mysql -V | awk '{print($5)}' | cut -d ',' -f1)
	local ee_mysql_port=$(mysql -e "show variables" | grep ^port | awk '{print($2)}')
	local ee_mysql_socket=$(mysql -e "show variables" | grep "^socket" | awk '{print($2)}')
	local ee_mysql_data_dir=$(mysql -e "show variables" | grep datadir | awk '{print($2)}')
	local ee_mysql_wait_timeout=$(mysql -e "show variables" | grep ^wait_timeout | awk '{print($2)}')
	local ee_mysql_interactive_timeout=$(mysql -e "show variables" | grep ^interactive_timeout | awk '{print($2)}')
	local ee_mysql_max_connections=$(mysql -e "show variables" | grep "^max_connections" | awk '{print($2)}')
	local ee_mysql_max_used_connections=$(mysql -e "show global status" | grep Max_used_connections | awk '{print($2)}')

	ee_lib_echo
	ee_lib_echo "MySQL ($ee_mysql_version) on $EE_MYSQL_HOST:"
	ee_lib_echo_escape "port\t\t\t\t \033[37m$ee_mysql_port"
	ee_lib_echo_escape "wait_timeout\t\t\t \033[37m$ee_mysql_wait_timeout"
	ee_lib_echo_escape "interactive_timeout\t\t \033[37m$ee_mysql_interactive_timeout"
	ee_lib_echo_escape "max_used_connections\t\t \033[37m$ee_mysql_max_used_connections/$ee_mysql_max_connections"
	ee_lib_echo_escape "datadir\t\t\t\t \033[37m$ee_mysql_data_dir"
	ee_lib_echo_escape "socket\t\t\t\t \033[37m$ee_mysql_socket"
}
