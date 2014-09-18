# Install EasyEngine (ee) admin utilities

function ee_ven_install_utils()
{
	dpkg -l | grep php5-fpm &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		# Setup phpMemcachedAdmin
		if [ ! -d /var/www/22222/htdocs/cache/memcache ];then
			# Create memcache directory
			mkdir -p /var/www/22222/htdocs/cache/memcache \
			|| ee_lib_error "Unable to create /var/www/22222/htdocs/cache/memcache directory, exit status = " $?

			# Download phpMemcachedAdmin
			ee_lib_echo "Installing phpMemcachedAdmin, please wait..."
			wget --no-check-certificate -cqO /var/www/22222/htdocs/cache/memcache/memcache.tar.gz http://phpmemcacheadmin.googlecode.com/files/phpMemcachedAdmin-1.2.2-r262.tar.gz \
			|| ee_lib_error "Unable to download phpMemcachedAdmin, exit status = " $?

			# Extract phpMemcachedAdmin
			tar -zxf /var/www/22222/htdocs/cache/memcache/memcache.tar.gz -C /var/www/22222/htdocs/cache/memcache

			# Remove unwanted file
			rm -f /var/www/22222/htdocs/cache/memcache/memcache.tar.gz
		fi

		# Nginx FastCGI cleanup
		if [ ! -d /var/www/22222/htdocs/cache/nginx ]; then
			mkdir -p /var/www/22222/htdocs/cache/nginx \
			|| ee_lib_error "Unable to create /var/www/22222/htdocs/cache/nginx Directory, exit status = " $?

			# Download nginx FastCGI cleanup
			ee_lib_echo "Downloading nginx FastCGI cleanup script, please wait..."
			wget --no-check-certificate -cqO /var/www/22222/htdocs/cache/nginx/clean.php https://raw.githubusercontent.com/rtCamp/eeadmin/master/cache/nginx/clean.php \
			|| ee_lib_error "Unable to download nginx FastCGI cleanup script, exit status = " $?
		fi

		# Setup opcache
		if [ ! -d /var/www/22222/htdocs/cache/opcache ]; then
			mkdir -p /var/www/22222/htdocs/cache/opcache \
			|| ee_lib_error "Unable to create /var/www/22222/htdocs/cache/opcache directory, exit status = " $?

			# Download opcache tools
			ee_lib_echo "Downloading OPcache, please wait..."
			wget --no-check-certificate -cqO /var/www/22222/htdocs/cache/opcache/opcache.php https://raw.github.com/rlerdorf/opcache-status/master/opcache.php \
			|| ee_lib_error "Unable to download opcache.php"
			wget --no-check-certificate -cqO /var/www/22222/htdocs/cache/opcache/opgui.php https://raw.github.com/amnuts/opcache-gui/master/index.php \
			|| ee_lib_error "Unable to download opgui.php"
			wget --no-check-certificate -cqO /var/www/22222/htdocs/cache/opcache/ocp.php https://gist.github.com/ck-on/4959032/raw/0b871b345fd6cfcd6d2be030c1f33d1ad6a475cb/ocp.php \
			|| ee_lib_error "Unable to download ocp.php"
		fi

		# PHP5-FPM status page
		if [ ! -d /var/www/22222/htdocs/fpm/status/ ]; then
			mkdir -p /var/www/22222/htdocs/fpm/status/ \
			|| ee_lib_error "Unable to create /var/www/22222/htdocs/fpm/status/ directory, exit status = " $?
			touch /var/www/22222/htdocs/fpm/status/{php,debug}
		fi

		# Setup Webgrind
		if [ ! -d /var/www/22222/htdocs/php/webgrind/ ]; then
			mkdir -p mkdir -p /var/www/22222/htdocs/php/webgrind/ \
			||  ee_lib_error "Unable to create /var/www/22222/htdocs/php/webgrind/ directory, exit status = " $?

			# Clone Webgrind
			ee_lib_echo "Cloning Webgrind, please wait..."
			git clone https://github.com/jokkedk/webgrind.git /var/www/22222/htdocs/php/webgrind/ &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to clone Webgrind, exit status = " $?
			sed -i "s'/usr/local/bin/dot'/usr/bin/dot'" /var/www/22222/htdocs/php/webgrind/config.php
		fi

		# phpinfo()
		echo -e "<?php \n\t phpinfo(); \n?>" &>> /var/www/22222/htdocs/php/info.php
	fi
	mysqladmin ping &> /dev/null
	if [ $? -eq 0 ]; then
		# Setup Anemometer
		if [ ! -d /var/www/22222/htdocs/db/anemometer ]; then
			mkdir -p /var/www/22222/htdocs/db/anemometer/ \
			|| ee_lib_error "Unable to create /var/www/22222/htdocs/db/anemometer/ directory, exit status = " $?

			# Clone Anemometer
			ee_lib_echo "Cloning Anemometer, please wait..."
			git clone https://github.com/box/Anemometer.git /var/www/22222/htdocs/db/anemometer &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to clone Anemometer, exit status = " $?

			# Setup Anemometer
			mysql < /var/www/22222/htdocs/db/anemometer/install.sql \
			|| ee_lib_error "Unable to import Anemometer database, exit status = " $?

			ee_anemometer_pass=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)

			# Grant select privileges for anemometer
			mysql -e "grant select on *.* to 'anemometer'@'$EE_MYSQL_GRANT_HOST'" ;

			# Grant all privileges for slow_query_log database.
			mysql -e "grant all on slow_query_log.* to 'anemometer'@'$EE_MYSQL_GRANT_HOST' IDENTIFIED BY '$ee_anemometer_pass';"

			# Anemometer configuration
			cp /var/www/22222/htdocs/db/anemometer/conf/sample.config.inc.php /var/www/22222/htdocs/db/anemometer/conf/config.inc.php \
			|| ee_lib_error "Unable to copy Anemometer configuration file, exit status = " $?

			sed -i "s/root/anemometer/g" /var/www/22222/htdocs/db/anemometer/conf/config.inc.php
			sed -i "/password/ s/''/'$ee_anemometer_pass'/g" /var/www/22222/htdocs/db/anemometer/conf/config.inc.php

			# Generate ee_lib_import_slow_log function
			> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
			dpkg --compare-versions $(pt-query-digest --version | awk '{print $2 }') ge 2.2
			if [ $? -eq 0 ]; then
				echo -e "# Import MySQL slow log to Anememoter" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "function ee_lib_import_slow_log()\n{" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\tpt-query-digest --user=anemometer --password=$ee_anemometer_pass \\" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\t--review D=slow_query_log,t=global_query_review \\" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\t--history D=slow_query_log,t=global_query_review_history \\" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\t--no-report --limit=0% --filter=\" \\\$event->{Bytes} = length(\\\$event->{arg}) and \\\$event->{hostname}="\\\"$EE_MYSQL_GRANT_HOST\\\"\" /var/log/mysql/mysql-slow.log >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "}" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
			else
				echo -e "# Import MySQL slow log to Anememoter" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "function ee_lib_import_slow_log()\n{" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\tpt-query-digest --user=anemometer --password=$ee_anemometer_pass \\" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\t--review-history D=slow_query_log,t=global_query_review \\" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\t--history D=slow_query_log,t=global_query_review_history \\" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "\t--no-report --limit=0% --filter=\" \\\$event->{Bytes} = length(\\\$event->{arg}) and \\\$event->{hostname}="\\\"$EE_MYSQL_GRANT_HOST\\\"\" /var/log/mysql/mysql-slow.log >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
				echo -e "}" >> /usr/local/lib/easyengine/lib/ee_lib_import_slow_log.sh
			fi

			# Download pt-query-advisor Fixed #189
			wget -q http://bazaar.launchpad.net/~percona-toolkit-dev/percona-toolkit/2.1/download/head:/ptquerydigest-20110624220137-or26tn4expb9ul2a-16/pt-query-digest -O /usr/bin/pt-query-advisor \
			|| ee_lib_error "Unable to copy download pt-query-advisor, exit status = " $?
			chmod 0755 /usr/bin/pt-query-advisor

			# Enable pt-query-advisor plugin in Anemometer
			sed -i "s/#	'query_advisor'/	'query_advisor'/" /var/www/22222/htdocs/db/anemometer/conf/config.inc.php \
			|| ee_lib_error "Unable to to activate pt-query-advisor plugin, exit status = " $?

		fi
	fi
	# Change permission
	chown -R $EE_PHP_USER:$EE_PHP_USER /var/www/22222 \
	|| ee_lib_error "Unable to change ownership for /var/www/22222"
}
