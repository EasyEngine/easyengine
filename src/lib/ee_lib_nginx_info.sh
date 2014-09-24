# NGINX information

function ee_lib_nginx_info()
{
	ee_lib_package_check $EE_NGINX_PACKAGE
	if [ "$EE_PACKAGE_NAME" != "" ]; then
		local ee_nginx_version=$(nginx -v 2>&1 | cut -d':' -f2 | cut -d' ' -f2 | cut -d'/' -f2)
		local ee_nginx_user=$(grep ^user /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
		local ee_nginx_processes=$(grep worker_processes /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
		local ee_nginx_connections=$(grep worker_connections /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
		local ee_nginx_keep_alive=$(grep keepalive_timeout /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
		local ee_fastcgi_timeout=$(grep fastcgi_read_timeout /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
		local ee_clinet_max_body_size=$(grep client_max_body_size /etc/nginx/nginx.conf | cut -d' ' -f2 | cut -d';' -f1)
		local ee_nginx_allowed_ip_add=$(grep ^allow /etc/nginx/common/acl.conf | cut -d' ' -f2 | cut -d';' -f1 | tr '\n' ' ')

		ee_lib_echo
		ee_lib_echo
		ee_lib_echo "NGINX ($ee_nginx_version):"
		ee_lib_echo_escape "user\t\t\t\t \033[37m$ee_nginx_user"
		ee_lib_echo_escape "worker_processes\t\t \033[37m$ee_nginx_processes"
		ee_lib_echo_escape "worker_connections\t\t \033[37m$ee_nginx_connections"
		ee_lib_echo_escape "keepalive_timeout\t\t \033[37m$ee_nginx_keep_alive"
		ee_lib_echo_escape "fastcgi_read_timeout\t\t \033[37m$ee_fastcgi_timeout"
		ee_lib_echo_escape "client_max_body_size\t\t \033[37m$ee_clinet_max_body_size"
		ee_lib_echo_escape "allow\t\t\t\t \033[37m$ee_nginx_allowed_ip_add"
	else
		ee_lib_echo "NGINX not installed"
	fi
}
