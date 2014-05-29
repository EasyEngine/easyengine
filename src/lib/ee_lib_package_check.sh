# Check the specified package is installed or not

function ee_lib_package_check()
{
	local ee_package
	
	for ee_package in $@;do
		dpkg --get-selections | grep -v deinstall | grep $ee_package &>> $EE_COMMAND_LOG

		# Generate a list of not installed package
		if [ $? -ne 0 ]; then
			EE_PACKAGE_NAME="$EE_PACKAGE_NAME $ee_package"
		fi

	done
}
