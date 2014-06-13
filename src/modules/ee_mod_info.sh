# Execute: ee info

function ee_mod_info()
{
	# Nginx Information
	local ee_nginx_ver=$(nginx -v 2>&1 | cut -d':' -f2 | cut -d' ' -f2)
	local ee_nginx_user=$(grep ^user /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
	local ee_nginx_processes=$(grep worker_processes /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
	local ee_nginx_connections=$(grep worker_connections /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
	local ee_nginx_keep_alive=$(grep keepalive_timeout /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
	local ee_fastcgi_timeout=$(grep fastcgi_read_timeout /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
	local ee_clinet_max_body_size=$(grep client_max_body_size /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
	local ee_nginx_allowed_ip_add=$(grep ^allow /etc/nginx/common/acl.conf | cut -d' ' -f2 | cut -d';' -f1 | tr '\n' ' ')

	ee_lib_echo
	ee_lib_echo "Nginx ($ee_nginx_ver) Information:"
	ee_lib_echo_escape "Nginx User:\t\t\t \033[37m$ee_nginx_user"
	ee_lib_echo_escape "Nginx worker_processes:\t\t \033[37m$ee_nginx_processes"
	ee_lib_echo_escape "Nginx worker_connections:\t \033[37m$ee_nginx_connections"
	ee_lib_echo_escape "Nginx keepalive_timeout:\t \033[37m$ee_nginx_keep_alive"
	ee_lib_echo_escape "Nginx fastcgi_read_timeout:\t \033[37m$ee_fastcgi_timeout"
	ee_lib_echo_escape "Nginx client_max_body_size:\t \033[37m$ee_clinet_max_body_size"
	ee_lib_echo_escape "Nginx Allowed IP Address:\t \033[37m$ee_nginx_allowed_ip_add"

	# PHP Information 
	# Collect Information From php.ini
	local ee_php_version=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'+' -f1)
	local ee_php_memory=$(grep ^memory_limit /etc/php5/fpm/php.ini | awk '{print $3}')
	local ee_php_expose=$(grep ^expose_php /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)
	local ee_php_post_max_size=$(grep post_max_size /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)
	local ee_php_upload_max_filesize=$(grep upload_max_filesize /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)
	local ee_php_max_execution_time=$(grep max_execution_time /etc/php5/fpm/php.ini | cut -d'=' -f2 | cut -d' ' -f2)

	# Collect Information From www.conf
	local ee_php_ping_path=$(grep ^ping.path /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_status_path=$(grep ^pm.status_path /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_process_manager=$(grep "^pm =" /etc/php5/fpm/pool.d/www.conf | awk '{print $3}')
	local ee_php_max_requests=$(grep ^pm.max_requests /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_max_children=$(grep ^pm.max_children /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_start_servers=$(grep ^pm.start_servers /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_min_spare_servers=$(grep ^pm.min_spare_servers /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_max_spare_servers=$(grep ^pm.max_spare_servers /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_request_terminate_timeout=$(grep ^request_terminate_timeout /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)
	local ee_php_listen=$(grep '^listen =' /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2| cut -d' ' -f2)

	ee_lib_echo
	ee_lib_echo
	ee_lib_echo "PHP ($ee_php_version) Information:"
	ee_lib_echo_escape "PHP User:\t\t\t \033[37m$EE_PHP_USER"
	ee_lib_echo_escape "PHP expose_php:\t\t\t \033[37m$ee_php_expose"
	ee_lib_echo_escape "PHP memory_limit:\t\t \033[37m$ee_php_memory"
	ee_lib_echo_escape "PHP post_max_size:\t\t \033[37m$ee_php_post_max_size"
	ee_lib_echo_escape "PHP upload_max_filesize:\t \033[37m$ee_php_upload_max_filesize"
	ee_lib_echo_escape "PHP max_execution_time:\t\t \033[37m$ee_php_max_execution_time\n"

	ee_lib_echo_escape "PHP ping.path:\t\t\t \033[37m$ee_php_ping_path"
	ee_lib_echo_escape "PHP pm.status_path:\t\t \033[37m$ee_php_status_path"
	ee_lib_echo_escape "PHP process manager:\t\t \033[37m$ee_php_process_manager"
	ee_lib_echo_escape "PHP pm.max_requests:\t\t \033[37m$ee_php_max_requests"
	ee_lib_echo_escape "PHP pm.max_children:\t\t \033[37m$ee_php_max_children"
	ee_lib_echo_escape "PHP pm.start_servers:\t\t \033[37m$ee_php_start_servers"
	ee_lib_echo_escape "PHP pm.min_spare_servers:\t \033[37m$ee_php_min_spare_servers"
	ee_lib_echo_escape "PHP pm.max_spare_servers:\t \033[37m$ee_php_max_spare_servers"
	ee_lib_echo_escape "PHP request_terminate_timeout:\t \033[37m$ee_php_request_terminate_timeout"
	ee_lib_echo_escape "PHP Fastcgi Listen:\t\t \033[37m$ee_php_listen"

	# MySQL Information
	local ee_mysql_version=$(mysql -V | awk '{print($5)}' | cut -d ',' -f1)
	local ee_mysql_port=$(mysql -e "show variables" | grep ^port | awk '{print($2)}')
	local ee_mysql_socket=$(mysql -e "show variables" | grep "^socket" | awk '{print($2)}')
	local ee_mysql_data_dir=$(mysql -e "show variables" | grep datadir | awk '{print($2)}')
	local ee_mysql_wait_timeout=$(mysql -e "show variables" | grep ^wait_timeout | awk '{print($2)}')
	local ee_mysql_interactive_timeout=$(mysql -e "show variables" | grep ^interactive_timeout | awk '{print($2)}')
	local ee_mysql_max_connections=$(mysql -e "show variables" | grep "^max_connections" | awk '{print($2)}')
	local ee_mysql_max_used_connections=$(mysql -e "show global status" | grep Max_used_connections | awk '{print($2)}')

	ee_lib_echo
	ee_lib_echo
	ee_lib_echo "MySQL ($ee_mysql_version) Information:"
	ee_lib_echo_escape "MySQL User:\t\t\t \033[37m$EE_MYSQL_USER"
	ee_lib_echo_escape "MySQL port:\t\t\t \033[37m$ee_mysql_port"
	ee_lib_echo_escape "MySQL wait_timeout:\t\t \033[37m$ee_mysql_wait_timeout"
	ee_lib_echo_escape "MySQL interactive_timeout:\t \033[37m$ee_mysql_interactive_timeout"
	ee_lib_echo_escape "MySQL Max_used_connections:\t \033[37m$ee_mysql_max_used_connections/$ee_mysql_max_connections"
	ee_lib_echo_escape "MySQL datadir:\t\t\t \033[37m$ee_mysql_data_dir"
	ee_lib_echo_escape "MySQL socket:\t\t\t \033[37m$ee_mysql_socket"

	# Common Locations:
	ee_lib_echo
	ee_lib_echo
	ee_lib_echo "EasyEngine ($EE_VERSION) Common Locations:"
	ee_lib_echo_escape "phpMyAdmin:\t\t\t \033[37mhttp://example.com/pma"
	ee_lib_echo_escape "PHP Status:\t\t\t \033[37mhttp://example.com/status"
	ee_lib_echo_escape "Nginx Status:\t\t\t \033[37mhttp://example.com/nginx_status"
	ee_lib_echo_escape "EasyEngine Log File:\t\t \033[37m/var/log/easyengine/install.log"
	ee_lib_echo_escape "EasyEngine Configuration File:\t \033[37m/etc/easyengine/ee.conf"

}
