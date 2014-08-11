# Execute: ee stack status

function ee_mod_stack_status()
{
	# Detect operating system
	local ee_operating_system=$(lsb_release -d | awk '{print $2,$3,$4}')

	# Detect system load and processes
	local ee_system_load=$(cat /proc/loadavg | awk '{print $1}')
	local ee_system_processes=$(ps ax | wc -l)

	# Uses of / partition and users logged in
	local ee_logged_in_users=$(w -h | wc -l)
	local ee_root_usage=$(df -h | grep /$ | head -1 | awk '{print $5}')

	# Memory uses
	local ee_memory_total=$(free | grep Mem: | awk '{print $2}')
	local ee_memory_used=$(free | grep Mem: | awk '{print $3}')
	local ee_memory_buffers=$(free | grep Mem: | awk '{print $6}')
	local ee_memory_cache=$(free | grep Mem: | awk '{print $7}')
	local ee_memory_usage=$(echo "($ee_memory_used-$ee_memory_buffers-$ee_memory_cache)*100/$ee_memory_total" | bc -l | cut -d'.' -f1)

	# Swap uses
	local ee_swap_total=$(free | grep Swap: | awk '{print $2}')
	if [[ $ee_swap_total > 0 ]]; then
		local ee_swap_used=$(free | grep Swap: | awk '{print $3}')
		local ee_swap_usage=$(echo "scale=2; $ee_swap_used*100/$ee_swap_total" | bc -l)%
	else
		local ee_swap_usage=$(echo "N/A")
	fi

	# Service status
	local ee_nginx_status=$(service nginx status 2> /dev/null 2>> $EE_COMMAND_LOG| grep 'nginx is running' \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")
	local ee_php_status=$(service php5-fpm status 2> /dev/null | grep running \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")
	local ee_mysql_status=$(mysqladmin ping \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")
	local ee_postfix_status=$(service postfix status 2> /dev/null | grep 'postfix is running' \
	&>> $EE_COMMAND_LOG && ee_lib_echo "Running" || ee_lib_echo_fail "Stopped")

	ee_lib_echo
	ee_lib_echo
	ee_lib_echo_info "  System information as of $(/bin/date)"
	ee_lib_echo
	ee_lib_echo_escape "  System load:\t$ee_system_load\t\t  Processes:\t\t$ee_system_processes"
	ee_lib_echo_escape "  Usage of /:\t$ee_root_usage\t\t  Users logged in:\t$ee_logged_in_users"
	ee_lib_echo_escape "  Memory usage:\t$ee_memory_usage%\t\t  Swap usage:\t\t$ee_swap_usage"
	ee_lib_echo
	ee_lib_echo_info "  Service status information"
	ee_lib_echo
	ee_lib_echo_escape "  Nginx:\t\t$ee_nginx_status"
	ee_lib_echo_escape "  PHP5-FPM:\t$ee_php_status"
	ee_lib_echo_escape "  MySQL:\t\t$ee_mysql_status"
	ee_lib_echo_escape "  Postfix:\t$ee_postfix_status"
	ee_lib_echo
	ee_lib_echo
}
