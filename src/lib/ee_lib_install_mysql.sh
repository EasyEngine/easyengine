# Install mysql package

ee_lib_install_mysql()
{
	# Mysql password only set if mysql is not installed
	# if mysql is installed don't set wrong password in ~/.my.cnf
	ee_lib_package_check mysql-server
	if [ -n $PACKAGE_NAME ]; then

		# setting up mysql password
		local ee_mysql_auto_pass=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)
		debconf-set-selections <<< "mysql-server mysql-server/root_password password $ee_mysql_auto_pass"
		debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $ee_mysql_auto_pass"

		# Generate ~/.my.cnf
		echo -e "[client]\nuser=root\npassword=$ee_mysql_auto_pass" > ~/.my.cnf
	fi

	ee_lib_echo "Installing MySQL, Please Wait..."
	$EE_APT_GET install mysql-server mysqltuner percona-toolkit \
	|| ee_lib_error "Unable To Install MySQL"
}