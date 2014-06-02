# Setup mysql

ee_lib_setup_mysql()
{
	# personal settings for mysql
	ee_lib_echo "Updating MySQL Configuration Files, Please Wait..."

	# Decrease mysql wait timeout
	sed -i "/#max_connections/a wait_timeout = 30 \ninteractive_timeout = 60" /etc/mysql/my.cnf
}