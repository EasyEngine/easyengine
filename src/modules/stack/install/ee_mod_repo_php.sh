# Setup php5-fpm repository

function ee_mod_repo_php()
{
	# Ubuntu
	if [ "$EE_LINUX_DISTRO" == "Ubuntu" ]; then

		# Add ondrej php5 launchpad repository
		ee_lib_echo "Adding Ondrej PHP5 launchpad repository, please wait..."
		add-apt-repository -y ppa:ondrej/php5 &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Unable to add ondrej php5 launchpad repository, exit status = " $?

	# Debian 6
	elif [ "$EE_DEBIAN_VERSION" == "squeeze" ]; then

		ee_lib_echo "Adding Dotdeb PHP5.4 repository, please wait..."
		echo "deb http://packages.dotdeb.org $(lsb_release -sc)-php54 all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -sc)-php54.list \
		|| ee_lib_error "Unable to add Dotdeb PHP5.4 repository, exit status = " $?

		# Fetch and install Dotdeb GnuPG key
		ee_lib_dotdeb

	# Debian 7
	elif [ "$EE_DEBIAN_VERSION" == "wheezy" ]; then

		ee_lib_echo "Adding Dotdeb PHP5.5 repository, please wait..."
		echo "deb http://packages.dotdeb.org $(lsb_release -sc)-php55 all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -sc)-php55.list \
		|| ee_lib_error "Unable to add Dotdeb PHP5.5 repository, exit status = " $?
		
		# Fetch and install dotdeb GnuPG key
		ee_lib_dotdeb

	fi
}
