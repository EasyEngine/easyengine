# Check the specified package is instlled or not
function PACKAGE_CHECK()
{
	for i in $@;do
		dpkg --get-selections | grep -v deinstall | grep $i &>> INSTALL_LOG
		# Generate a list of not installed package
		if [ $? -ne 0 ]; then
			PACKAGE_NAME="$PACKAGE_NAME $i"
		fi
	done
}
