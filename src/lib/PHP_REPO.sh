# Install php5-fpm
function PHP_REPO()
{
	# Check LINUX_DISTRO
	if [ "$LINUX_DISTRO" == "Ubuntu" ]; then
		# Add ondrej php5 launchpad repository
		ECHO_BLUE "Adding ondrej php5 launchpad repository, please wait..."
		add-apt-repository -y ppa:ondrej/php5 &>> $EE_LOG \
		|| EE_ERROR "Unable to add ondrej php5 launchpad repository"

	# Detect Debian version to select php repository
	elif [ "$LINUX_DISTRO" == "Debian" ]; then
		DEBIAN_VERSION=$(lsb_release -r | awk '{print($2)}' | cut -d'.' -f1)

		# Add dotdeb php5.x repository
		if [ $DEBIAN_VERSION -eq 6 ]; then
			ECHO_BLUE "Adding dotdeb php5.4 repository, please wait..."
			echo "deb http://packages.dotdeb.org $(lsb_release -c | awk '{print($2)}')-php54 all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -c | awk '{print($2)}')-php54.list \
			|| EE_ERROR "Unable to add dotdeb php5.4 repository"
		elif [ $DEBIAN_VERSION -eq 7 ]; then
			ECHO_BLUE "Adding dotdeb php5.5 repository, please wait..."
			echo "deb http://packages.dotdeb.org $(lsb_release -c | awk '{print($2)}')-php55 all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -c | awk '{print($2)}')-php55.list \
			|| EE_ERROR "Unable to add dotdeb php5.5 repository"
		fi
		
		# Fetch and install dotdeb GnuPG key
		DOT_DEB_GPG_KEY
	fi
}
