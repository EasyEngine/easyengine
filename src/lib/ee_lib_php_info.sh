# PHP information 
function ee_lib_php_info()
{
	#Collect information from php.ini
	local ee_php_version=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'+' -f1)
	local ee_php_memory=$(grep ^memory_limit /etc/php5/fpm/php.ini | awk '{print $3}')
	local ee_php_expose=$(grep ^expose_php /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)
	local ee_php_post_max_size=$(grep post_max_size /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)
	local ee_php_upload_max_filesize=$(grep upload_max_filesize /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)
	local ee_php_max_execution_time=$(grep max_execution_time /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)


	ee_lib_echo
	ee_lib_echo "PHP ($ee_php_version):"
	ee_lib_echo_escape "user\t\t\t\t \033[37m$EE_PHP_USER"
	ee_lib_echo_escape "expose_php\t\t\t \033[37m$ee_php_expose"
	ee_lib_echo_escape "memory_limit\t\t\t \033[37m$ee_php_memory"
	ee_lib_echo_escape "post_max_size\t\t\t \033[37m$ee_php_post_max_size"
	ee_lib_echo_escape "upload_max_filesize\t\t \033[37m$ee_php_upload_max_filesize"
	ee_lib_echo_escape "max_execution_time\t\t \033[37m$ee_php_max_execution_time"


	#Collect information from $ee_php_pool and debug.conf
	for ee_php_pool in www.conf debug.conf;do
		local ee_php_ping_path=$(grep ^ping.path /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_php_status_path=$(grep ^pm.status_path /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_php_process_manager=$(grep "^pm =" /etc/php5/fpm/pool.d/$ee_php_pool | awk '{print $3}')
		local ee_php_max_requests=$(grep ^pm.max_requests /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_php_max_children=$(grep ^pm.max_children /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_php_start_servers=$(grep ^pm.start_servers /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_php_min_spare_servers=$(grep ^pm.min_spare_servers /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_php_max_spare_servers=$(grep ^pm.max_spare_servers /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_php_request_terminate_timeout=$(grep ^request_terminate_timeout /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		local ee_xdebug_check=$( grep "php_admin_flag\[xdebug.profiler_enable_trigger\]" /etc/php5/fpm/pool.d/$ee_php_pool | grep on &> /dev/null && echo on || echo off)
		local ee_php_listen=$(grep '^listen =' /etc/php5/fpm/pool.d/$ee_php_pool | cut -d'=' -f2| cut -d' ' -f2)
		
		ee_lib_echo
		ee_lib_echo "Information about $ee_php_pool"
		ee_lib_echo_escape "ping.path\t\t\t \033[37m$ee_php_ping_path"
		ee_lib_echo_escape "pm.status_path\t\t\t \033[37m$ee_php_status_path"
		ee_lib_echo_escape "process_manager\t\t\t \033[37m$ee_php_process_manager"
		ee_lib_echo_escape "pm.max_requests\t\t\t \033[37m$ee_php_max_requests"
		ee_lib_echo_escape "pm.max_children\t\t\t \033[37m$ee_php_max_children"
		ee_lib_echo_escape "pm.start_servers\t\t \033[37m$ee_php_start_servers"
		ee_lib_echo_escape "pm.min_spare_servers\t\t \033[37m$ee_php_min_spare_servers"
		ee_lib_echo_escape "pm.max_spare_servers\t\t \033[37m$ee_php_max_spare_servers"
		ee_lib_echo_escape "request_terminate_timeout\t \033[37m$ee_php_request_terminate_timeout"
		ee_lib_echo_escape "xdebug.profiler_enable_trigger\t \033[37m$ee_xdebug_check"
		ee_lib_echo_escape "listen\t\t\t\t \033[37m$ee_php_listen"
	done

}
