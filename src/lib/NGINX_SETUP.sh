# Setup nginx
function NGINX_SETUP()
{
	ECHO_BLUE "Setting up nginx, please wait..."

	grep "EasyEngine" /etc/nginx/nginx.conf &> /dev/null
	if [ $? -ne 0 ]; then
		# Adjust nginx worker_processes and worker_rlimit_nofile value
		sed -i "s/worker_processes.*/worker_processes auto;/" /etc/nginx/nginx.conf
		sed -i "/worker_processes/a \worker_rlimit_nofile 100000;" /etc/nginx/nginx.conf

		# Adjust nginx worker_connections and multi_accept
		sed -i "s/worker_connections.*/worker_connections 1024;/" /etc/nginx/nginx.conf
		sed -i "s/# multi_accept/multi_accept/" /etc/nginx/nginx.conf

		# Disable nginx version
		# Set custome header
		# SSL Settings
		sed -i "s/http {/http {\n\t##\n\t# EasyEngine Settings\n\t##\n\n\tserver_tokens off;\n\treset_timedout_connection on;\n\tadd_header X-Powered-By "EasyEngine";\n\tadd_header rt-Fastcgi-Cache \$upstream_cache_status;\n\n\t# Limit Request\n\tlimit_req_status 403;\n\tlimit_req_zone \$binary_remote_addr zone=one:10m rate=1r\/s;\n\n\t# Proxy Settings\n\t# set_real_ip_from\tproxy-server-ip;\n\t# real_ip_header\tX-Forwarded-For;\n\n\tfastcgi_read_timeout 300;\n\tclient_max_body_size 100m;\n\n\t# SSL Settings\n\tssl_session_cache shared:SSL:20m;\n\tssl_session_timeout 10m;\n\tssl_prefer_server_ciphers on;\n\tssl_ciphers HIGH:\!aNULL:\!MD5:\!kEDH;\n\n/" /etc/nginx/nginx.conf

		# Adjust nginx keepalive_timeout
		sed -i "s/keepalive_timeout.*/keepalive_timeout 30;/" /etc/nginx/nginx.conf

		# Adjust nginx log format
		sed -i "s/error_log.*/error_log \/var\/log\/nginx\/error.log;\n\n\tlog_format rt_cache '\$remote_addr \$upstream_response_time \$upstream_cache_status [\$time_local] '\n\t\t'\$http_host \"\$request\" \$status \$body_bytes_sent '\n\t\t'\"\$http_referer\" \"\$http_user_agent\"';/" /etc/nginx/nginx.conf

		# Enable Gunzip
		sed -i "s/# gzip/gzip/" /etc/nginx/nginx.conf
	fi

	# Create directory if not exist
	if [ ! -d /etc/nginx/conf.d ]; then
		mkdir /etc/nginx/conf.d || EE_ERROR "Unable to create /etc/nginx/conf.d"
	fi

	if [ ! -d /etc/nginx/common ]; then
		mkdir /etc/nginx/common || EE_ERROR "Unable to create /etc/nginx/common"
	fi

	# Copy files
	cp -v /usr/share/easyengine/nginx/conf.d /usr/share/easyengine/nginx/common /etc/nginx

	# Setup port 22222
	cp -v /usr/share/easyengine/nginx/22222 /etc/nginx/sites-available/

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
	# Generate SSL Key
	ECHO_BLUE "Generating ssl private key, please wait..."
	openssl genrsa -out /var/www/22222/cert/22222.key 2048 &>> $EE_LOG \
	|| EE_ERROR "Unable to generate ssl private key for port 22222"

	ECHO_BLUE "Generating a certificate signing request (csr), please wait..."
	openssl req -new -batch -subj /commonName=127.0.0.1/ -key /var/www/22222/cert/22222.key -out /var/www/22222/cert/22222.csr &>> $EE_LOG \
	|| EE_ERROR "Unable to generate certificate signing request (csr) for port 22222"

	ECHO_BLUE "Removing passphrase from ssl private key, please wait..."
	mv /var/www/22222/cert/22222.key /var/www/22222/cert/22222.key.org
	openssl rsa -in /var/www/22222/cert/22222.key.org -out /var/www/22222/cert/22222.key &>> $EE_LOG \
	|| EE_ERROR "Unable to remove passphrase from ssl for port 22222"

	ECHO_BLUE "Generating ssl certificate, please wait..."
	openssl x509 -req -days 3652 -in /var/www/22222/cert/22222.csr -signkey /var/www/22222/cert/22222.key -out /var/www/22222/cert/22222.crt &>> $EE_LOG \
	|| EE_ERROR "Unable to generate ssl certificate for port 22222"

	# Whitelist ip address
	if [ -n "$IP_ADDRESS" ]; then
		for WHITELIST_IP_ADDRESS in $(echo $IP_ADDRESS)
		do
        	sed -i "/deny/i $(echo allow $WHITELIST_IP_ADDRESS\;)" /etc/nginx/common/acl.conf
		done
	fi

	# Set easyengine:easyengine as default http authentication
	printf "easyengine:$(openssl passwd -crypt easyengine 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
}
