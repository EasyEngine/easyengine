# Setup nginx

function ee_mod_setup_nginx()
{
	local ee_whitelist_ip_address
	
	ee_lib_echo "Setting up NGINX, please wait..."

	grep "EasyEngine" /etc/nginx/nginx.conf &>> /dev/null
	if [ $? -ne 0 ]; then

		# Adjust nginx worker_processes and worker_rlimit_nofile value
		sed -i "s/worker_processes.*/worker_processes auto;/" /etc/nginx/nginx.conf
		sed -i "/worker_processes/a \worker_rlimit_nofile 100000;" /etc/nginx/nginx.conf

		# Adjust nginx worker_connections and multi_accept
		sed -i "s/worker_connections.*/worker_connections 4096;/" /etc/nginx/nginx.conf
		sed -i "s/# multi_accept/multi_accept/" /etc/nginx/nginx.conf

		# Disable nginx version
		# Set custom header
		# SSL Settings
		sed -i "s/http {/http {\n\t##\n\t# EasyEngine Settings\n\t##\n\n\tserver_tokens off;\n\treset_timedout_connection on;\n\tadd_header X-Powered-By \"EasyEngine $EE_VERSION\";\n\tadd_header rt-Fastcgi-Cache \$upstream_cache_status;\n\n\t# Limit Request\n\tlimit_req_status 403;\n\tlimit_req_zone \$binary_remote_addr zone=one:10m rate=1r\/s;\n\n\t# Proxy Settings\n\t# set_real_ip_from\tproxy-server-ip;\n\t# real_ip_header\tX-Forwarded-For;\n\n\tfastcgi_read_timeout 300;\n\tclient_max_body_size 100m;\n\n\t# SSL Settings\n\tssl_session_cache shared:SSL:20m;\n\tssl_session_timeout 10m;\n\tssl_prefer_server_ciphers on;\n\tssl_ciphers HIGH:\!aNULL:\!MD5:\!kEDH;\n\n/" /etc/nginx/nginx.conf

		# Adjust nginx keepalive_timeout
		sed -i "s/keepalive_timeout.*/keepalive_timeout 30;/" /etc/nginx/nginx.conf

		# Adjust nginx log format
		sed -i "s/error_log.*/error_log \/var\/log\/nginx\/error.log;\n\n\tlog_format rt_cache '\$remote_addr \$upstream_response_time \$upstream_cache_status [\$time_local] '\n\t\t'\$http_host \"\$request\" \$status \$body_bytes_sent '\n\t\t'\"\$http_referer\" \"\$http_user_agent\"';/" /etc/nginx/nginx.conf

		# Enable Gun-zip
		sed -i "s/# gzip/gzip/" /etc/nginx/nginx.conf
	fi

	# Update EasyEngine version
	# Launchpad PPA already have above settings
	# On Ubuntu above block never executed
	sed -i "s/X-Powered-By.*/X-Powered-By \"EasyEngine $EE_VERSION\";/" /etc/nginx/nginx.conf 

	# Create directory if not exist
	if [ ! -d /etc/nginx/conf.d ]; then
		mkdir /etc/nginx/conf.d || ee_lib_error "Unable to create /etc/nginx/conf.d, exit status = " $?
	fi

	if [ ! -d /etc/nginx/common ]; then
		mkdir /etc/nginx/common || ee_lib_error "Unable to create /etc/nginx/common, exit status = " $?
	fi

	# Copy files
	cp -a /usr/share/easyengine/nginx/conf.d /usr/share/easyengine/nginx/common /etc/nginx

	# Setup port 22222
	cp /usr/share/easyengine/nginx/22222 /etc/nginx/sites-available/

	# Create a symbolic link for 22222
	if [ ! -L /etc/nginx/sites-enabled/22222 ]; then
		ln -s /etc/nginx/sites-available/22222 /etc/nginx/sites-enabled/
	fi

	# Setup logs for 22222
	if [ ! -d /var/www/22222/logs ]; then
		mkdir -p /var/www/22222/logs
		ln -s /var/log/nginx/22222.access.log /var/www/22222/logs/access.log
		ln -s /var/log/nginx/22222.error.log /var/www/22222/logs/error.log
	fi

	# Setup SSL
	# Create SSL certificate directory
	if [ ! -d /var/www/22222/cert ]; then
		mkdir /var/www/22222/cert
	fi
	
	# Generate SSL key
	ee_lib_echo "Generating SSL private key"
	openssl genrsa -out /var/www/22222/cert/22222.key 2048 &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to generate SSL private key for port 22222, exit status = " $?

	ee_lib_echo "Generating a certificate signing request (CSR)"
	openssl req -new -batch -subj /commonName=127.0.0.1/ -key /var/www/22222/cert/22222.key -out /var/www/22222/cert/22222.csr &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to generate certificate signing request (CSR) for port 22222, exit status = " $?

	ee_lib_echo "Removing pass phrase from SSL private key"
	mv /var/www/22222/cert/22222.key /var/www/22222/cert/22222.key.org
	openssl rsa -in /var/www/22222/cert/22222.key.org -out /var/www/22222/cert/22222.key &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to remove pass phrase from SSL for port 22222, exit status = " $?

	ee_lib_echo "Generating SSL certificate"
	openssl x509 -req -days 3652 -in /var/www/22222/cert/22222.csr -signkey /var/www/22222/cert/22222.key -out /var/www/22222/cert/22222.crt &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to generate SSL certificate for port 22222, exit status = " $?

	# White list IP address
	if [ -n "$EE_IP_ADDRESS" ]; then
		for ee_whitelist_ip_address in $(echo $EE_IP_ADDRESS);do
      sed -i "/deny/i $(echo allow $ee_whitelist_ip_address\;)" /etc/nginx/common/acl.conf
		done
	fi

	# Set easyengine:easyengine as default http authentication
	if [ ! -f /etc/nginx/htpasswd-ee ]; then
		printf "easyengine:$(openssl passwd -crypt easyengine 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
	fi
}
