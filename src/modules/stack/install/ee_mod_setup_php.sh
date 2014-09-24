# Setup php5-fpm

function ee_mod_setup_php()
{
	ee_lib_echo "Setting up PHP5, please wait..."

	# Custom php5 log directory
	if [ ! -d /var/log/php5/ ]; then
		mkdir -p /var/log/php5/ || ee_lib_error "Unable to create /var/log/PHP5/, exit status = " $?
	fi

	grep "EasyEngine" /etc/php5/fpm/php.ini &> /dev/null
	if [ $? -ne 0 ]; then

		local ee_time_zone=$(cat /etc/timezone | head -n1 | sed "s'/'\\\/'")

		# Adjust php.ini
		sed -i "s/\[PHP\]/[PHP]\n; EasyEngine/" /etc/php5/fpm/php.ini
		sed -i "s/expose_php.*/expose_php = Off/" /etc/php5/fpm/php.ini
		sed -i "s/post_max_size.*/post_max_size = 100M/" /etc/php5/fpm/php.ini
		sed -i "s/upload_max_filesize.*/upload_max_filesize = 100M/" /etc/php5/fpm/php.ini
		sed -i "s/max_execution_time.*/max_execution_time = 300/" /etc/php5/fpm/php.ini
		sed -i "s/;date.timezone.*/date.timezone = $ee_time_zone/" /etc/php5/fpm/php.ini

		# Change php5-fpm error log location
		sed -i "s'error_log.*'error_log = /var/log/php5/fpm.log'" /etc/php5/fpm/php-fpm.conf

		# Enable php status and ping
		sed -i "s/;ping.path/ping.path/" /etc/php5/fpm/pool.d/www.conf
		sed -i "s/;pm.status_path/pm.status_path/" /etc/php5/fpm/pool.d/www.conf

		# Adjust php5-fpm pool
		sed -i "s/;pm.max_requests/pm.max_requests/" /etc/php5/fpm/pool.d/www.conf
		sed -i "s/pm.max_children = 5/pm.max_children = ${EE_PHP_MAX_CHILDREN}/" /etc/php5/fpm/pool.d/www.conf
		sed -i "s/pm.start_servers = 2/pm.start_servers = 20/" /etc/php5/fpm/pool.d/www.conf
		sed -i "s/pm.min_spare_servers = 1/pm.min_spare_servers = 10/" /etc/php5/fpm/pool.d/www.conf
		sed -i "s/pm.max_spare_servers = 3/pm.max_spare_servers = 30/" /etc/php5/fpm/pool.d/www.conf
		sed -i "s/;request_terminate_timeout.*/request_terminate_timeout = 300/" /etc/php5/fpm/pool.d/www.conf
		sed -i "s/pm = dynamic/pm = ondemand/" /etc/php5/fpm/pool.d/www.conf \
		|| ee_lib_error "Unable to change process manager from dynamic to ondemand, exit status = " $?
		
		# Adjust php5-fpm listen
		sed -i "s'listen = /var/run/php5-fpm.sock'listen = 127.0.0.1:9000'" /etc/php5/fpm/pool.d/www.conf \
		|| ee_lib_error "Unable to change php5-fpm listen socket, exit status = " $?

		# Separate php5-fpm for ee debug command
		cp /etc/php5/fpm/pool.d/www.conf /etc/php5/fpm/pool.d/debug.conf

		sed -i "s'\[www\]'[debug]'" /etc/php5/fpm/pool.d/debug.conf \
		|| ee_lib_error "Unable to change debug pool name, exit status = " $?

		sed -i "s'listen = 127.0.0.1:9000'listen = 127.0.0.1:9001'" /etc/php5/fpm/pool.d/debug.conf \
		|| ee_lib_error "Unable to change listen = 127.0.0.1:9001 for debug pool, exit status = " $?
	
		sed -i "s';slowlog.*'slowlog = /var/log/php5/slow.log'"  /etc/php5/fpm/pool.d/debug.conf \
		|| ee_lib_error "Unable to change slowlog settings for debug pool, exit status = " $?

		sed -i "s';request_slowlog_timeout.*'request_slowlog_timeout = 10s'"  /etc/php5/fpm/pool.d/debug.conf \
		|| ee_lib_error "Unable to change request_slowlog_timeout for debug pool, exit status = " $?
		
		echo -e "php_admin_value[xdebug.profiler_output_dir] = /tmp/ \nphp_admin_value[xdebug.profiler_output_name] = cachegrind.out.%p-%H-%R \nphp_admin_flag[xdebug.profiler_enable_trigger] = on \nphp_admin_flag[xdebug.profiler_enable] = off" | tee -ai  /etc/php5/fpm/pool.d/debug.conf &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Unable to add xdebug settings for debug pool, exit status = " $?

		ee_lib_echo "Downloading GeoIP Database, please wait..."
		mkdir -p /usr/share/GeoIP
		wget -qO  /usr/share/GeoIP/GeoLiteCity.dat.gz /usr/share/GeoIP/GeoIPCity.dat http://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz
		gunzip /usr/share/GeoIP/GeoLiteCity.dat.gz
		mv /usr/share/GeoIP/GeoLiteCity.dat /usr/share/GeoIP/GeoIPCity.dat

		# Setup Zend OpCache as per RAM
		grep memory_consumption /etc/php5/fpm/conf.d/05-opcache.ini &> /dev/null 
		if [ $? -ne 0 ]; then
			sed -i "s/zend_extension=opcache.so/zend_extension=opcache.so\nopcache.memory_consumption=${EE_OPCACHE_SIZE}\nopcache.max_accelerated_files=50000/" /etc/php5/fpm/conf.d/05-opcache.ini \
			|| ee_lib_error "Unable to change opcache.memory_consumption, exit status = " $?
		fi

		# Setup PHP Memcache as per RAM
		sed "s/-m.*/-m ${EE_MEMCACHE_SIZE}/" /etc/memcached.conf \
		|| ee_lib_error "Unable to change Memcache memory value, exit status = " $?	
	fi
}
