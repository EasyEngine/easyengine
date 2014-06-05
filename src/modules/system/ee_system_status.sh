# System status information

function ee_system_status()
{
	local ee_operating_system=$(lsb_release -d | awk '{print $2,$3,$4}')
	local ee_system_load=$(cat /proc/loadavg | awk '{print $1}')
	local ee_system_processes=$(ps ax | wc -l)
	local ee_memory_total=$(free | grep Mem: | awk '{print $2}')
	local ee_memory_used=$(free | grep Mem: | awk '{print $3}')
	local ee_memory_buffers=$(free | grep Mem: | awk '{print $6}')
	local ee_memory_cache=$(free | grep Mem: | awk '{print $7}')
	local ee_swap_total=$(free | grep Swap: | awk '{print $2}')
	local ee_memory_usage=$(echo "($ee_memory_used-$ee_memory_buffers-$ee_memory_cache)*100/$ee_memory_total" | bc -l | cut -d'.' -f1)
	if [[ $ee_swap_total > 0 ]]; then
		local ee_swap_used=$(free | grep Swap: | awk '{print $3}')
		local ee_swap_usage=$(echo "$ee_swap_used*100/$ee_swap_total" | bc -l | cut -d'.' -f1)
	else
		local ee_swap_usage=$(echo "N/A")
	fi
	local ee_logged_in_users=$(w -h | wc -l)
	local ee_root_usage=$(df -h | grep /$ | head -1 | awk '{print $5}')

	local ee_nginx_status=$(service nginx status | grep 'nginx is running' \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")
	local ee_php_status=$(service php5-fpm status | grep running \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")
	local ee_mysql_status=$(mysqladmin ping \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")
	local ee_postfix_status=$(service postfix status | grep 'postfix is running' \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")

	echo
	echo
	ee_lib_echo_info "  System information as of $(/bin/date)"
	echo
	echo -e "  System load:\t$ee_system_load\t\t  Processes:\t\t$ee_system_processes"
	echo -e "  Usage of /:\t$ee_root_usage\t\t  Users logged in:\t$ee_logged_in_users"
	echo -e "  Memory usage:\t$ee_memory_usage%\t\t  Swap usage:\t\t$ee_swap_usage"
	echo
	ee_lib_echo_info "  Service status information"
	echo
	echo -e "  Nginx:\t$ee_nginx_status"
	echo -e "  PHP5-FPM:\t$ee_php_status"
	echo -e "  MySQL:\t$ee_mysql_status"
	echo -e "  Postfix:\t$ee_postfix_status"
	echo
	echo
}
