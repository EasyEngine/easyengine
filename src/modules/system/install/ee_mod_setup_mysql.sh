# Setup MySQL

function ee_mod_setup_mysql()
{
	ee_lib_echo "Setting up MySQL, please wait..."

	# Setting wait_timeout = 30 & interactive_timeout = 60
	sed -i "/#max_connections/a wait_timeout = 30 \ninteractive_timeout = 60" /etc/mysql/my.cnf
}
