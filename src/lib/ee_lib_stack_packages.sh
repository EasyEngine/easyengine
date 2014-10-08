# Check & Install Packages

function ee_lib_stack_packages()
{
	local ee_stack_package
	
	for ee_stack_package in $@;do
		# Check NGINX installed & install if not
		if [ "$ee_stack_package" = "nginx" ]; then
			ee_lib_package_check $EE_NGINX_PACKAGE
			if [ "$EE_PACKAGE_NAME" != "" ]; then
				# The following command creates its own sub-shell
				# and our ee_lib_error function only exit from that sub-shell
				# so we need to exit from parent shell also
				ee stack install nginx || exit $?
			fi
		# Check PHP installed & install if not
		elif [ "$ee_stack_package" = "php" ]; then
			ee_lib_package_check php5-fpm
			if [ "$EE_PACKAGE_NAME" != "" ]; then
				# The following command creates its own sub-shell
				# and our ee_lib_error function only exit from that sub-shell
				# so we need to exit from parent shell also
				ee stack install php || exit $?
			fi
		# Check MySQL installed & install if not
		elif [ "$ee_stack_package" = "mysql" ]; then
			mysqladmin ping &>> $EE_COMMAND_LOG
			if [ $? -ne 0 ]; then
				# The following command creates its own sub-shell
				# and our ee_lib_error function only exit from that sub-shell
				# so we need to exit from parent shell also
				ee stack install mysql || exit $?
			fi
		# Check Postfix installed & install if not
		elif [ "$ee_stack_package" = "postfix" ]; then
			ee_lib_package_check postfix
			if [ "$EE_PACKAGE_NAME" != "" ]; then
				# The following command creates its own sub-shell
				# and our ee_lib_error function only exit from that sub-shell
				# so we need to exit from parent shell also
				ee stack install postfix || exit $?
			fi	
		fi
	done
}
