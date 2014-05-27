# Check the specified package is installed or not

function package_check()
{
	local package
	
	for package in $@;do
		dpkg --get-selections | grep -v deinstall | grep $package &>> $EE_LOG

		# Generate a list of not installed package
		if [ $? -ne 0 ]; then
			PACKAGE_NAME="$PACKAGE_NAME $package"
		fi

	done
}
