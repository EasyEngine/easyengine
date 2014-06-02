# Install MySQL Package

ee_lib_install_mysql()
{
	# Check mysql-server is installed or not
	ee_lib_package_check mysql-server

	# If mysql-server is not installed
	# Then set random mysql password for root user
	if [ -n $PACKAGE_NAME ]; then

		# setting up mysql password
		local ee_mysql_auto_pass=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)
		debconf-set-selections <<< "mysql-server mysql-server/root_password password $ee_mysql_auto_pass"
		debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $ee_mysql_auto_pass"

		# Generate ~/.my.cnf
		echo -e "[client]\nuser=root\npassword=$ee_mysql_auto_pass" > ~/.my.cnf4

	fi

	ee_lib_echo "Installing MySQL, please Wait..."
	$EE_APT_GET install mysql-server mysqltuner percona-toolkit \
	|| ee_lib_error "Unable to install MySQL, exit status = " $?
}
