# Check the specified package is installed or not

function ee_lib_package_check()
{
	# If nginx is not installed and php is installed
	# ee site create example.com --wp is tries to installl php as $EE_PACKAGE_NAME=nginx-custom
	EE_PACKAGE_NAME=""
	
	local ee_package
	
	for ee_package in $@;do
		dpkg --get-selections | grep -v deinstall | grep $ee_package &>> $EE_COMMAND_LOG

		# Generate a list of not installed package
		if [ $? -ne 0 ]; then
			EE_PACKAGE_NAME="$EE_PACKAGE_NAME $ee_package"
		fi

	done
}
