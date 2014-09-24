# Setup MySQL

function ee_mod_setup_mysql()
{
	ee_lib_echo "Setting up Percona MySQL, please wait..."

	# Setting wait_timeout = 30 & interactive_timeout = 60
	if [ ! -f /etc/mysql/my.cnf ]; then
		echo  -e "[mysqld] \nwait_timeout = 30 \ninteractive_timeout = 60" &>> /etc/mysql/my.cnf
	fi
}
