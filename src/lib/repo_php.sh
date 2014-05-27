# Setup php5-fpm repository

function repo_php()
{
	# Ubuntu
	if [ "$LINUX_DISTRO" == "Ubuntu" ]; then

		# Add ondrej php5 launchpad repository
		echo_blue "Adding ondrej php5 launchpad repository, please wait..."
		add-apt-repository -y ppa:ondrej/php5 &>> $EE_LOG \
		|| ee_error "Unable to add ondrej php5 launchpad repository, exit status = " $?

	# Debian 6
	elif [ $DEBIAN_VERSION -eq 6 ]; then

		echo_blue "Adding dotdeb php5.4 repository, please wait..."
		echo "deb http://packages.dotdeb.org $(lsb_release -c | awk '{print($2)}')-php54 all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -c | awk '{print($2)}')-php54.list \
		|| ee_error "Unable to add dotdeb php5.4 repository, exit status = " $?

		# Fetch and install dotdeb GnuPG key
		dot_deb_gpg_key

	# Debian 7
	elif [ $DEBIAN_VERSION -eq 7 ]; then

		echo_blue "Adding dotdeb php5.5 repository, please wait..."
		echo "deb http://packages.dotdeb.org $(lsb_release -c | awk '{print($2)}')-php55 all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -c | awk '{print($2)}')-php55.list \
		|| ee_error "Unable to add dotdeb php5.5 repository, exit status = " $?
		
		# Fetch and install dotdeb GnuPG key
		dot_deb_gpg_key

	fi
}
