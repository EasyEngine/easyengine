# Setup MySQL

function ee_mod_setup_mysql()
{
	ee_lib_echo "Setting up Percona MySQL, please wait..."

	# Setting wait_timeout = 30 & interactive_timeout = 60
	if [ ! -f /etc/mysql/my.cnf ]; then
		echo  -e "[mysqld] \nwait_timeout = 30 \ninteractive_timeout = 60 \nperformance_schema = 0" > /etc/mysql/my.cnf
	else
		grep "_timeout" /etc/mysql/my.cnf &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			sed -i "/#max_connections/a wait_timeout = 30 \ninteractive_timeout = 60 \nperformance_schema = 0" /etc/mysql/my.cnf
		fi
	fi
}
