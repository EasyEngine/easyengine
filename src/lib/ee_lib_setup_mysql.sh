# Setup MySQL

ee_lib_setup_mysql()
{
	ee_lib_echo "Setting up MySQL, please wait..."

	# Decrease mysql wait timeout
	sed -i "/#max_connections/a wait_timeout = 30 \ninteractive_timeout = 60" /etc/mysql/my.cnf
}
