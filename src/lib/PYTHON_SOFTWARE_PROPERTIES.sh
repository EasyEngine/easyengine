# Install add-apt-repository command
PYTHON-SOFTWARE-PROPERTIES()
{
	if [ "$LINUX_DISTRO" == "Ubuntu" ]; then
		# Install python-software-properties and software-properties-common
		ECHO_BLUE "Installing python-software-properties and software-properties-common, please wait..."
		$APT_GET install python-software-properties software-properties-common \
		|| EE_ERROR "Unable to install python-software-properties and software-properties-common"
	elif [ "$LINUX_DISTRO" == "Debian" ]; then
		# Install python-software-properties
		ECHO_BLUE "Installing python-software-properties, please wait..."
		$APT_GET install python-software-properties \
		|| EE_ERROR "Unable to install python-software-properties"
	fi
}
