# Install nginx
function NGINX_REPO()
{
	if [ "$LINUX_DISTRO" == "Ubuntu" ];	then
		# Add rtCamp nginx launchpad repository
		ECHO_BLUE "Adding rtCamp nginx launchpad repository, please wait..."
		add-apt-repository -y ppa:rtcamp/nginx &>> $EE_LOG \
		|| EE_ERROR "Unable to add rtCamp nginx launchpad repository"

		# Specify nginx package
		NGINX_PACKAGE=nginx-custom

	elif [ "$LINUX_DISTRO" == "Debian" ]; then
		# Add dotdeb nginx repository
		ECHO_BLUE "Adding dotdeb nginx repository, please wait..."
		echo "deb http://packages.dotdeb.org $(lsb_release -c | awk '{print($2)}') all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -c | awk '{print($2)}').list \
		|| EE_ERROR "Unable to add dotdeb nginx repository"

		# Fetch and install dotdeb GnuPG key
		DOT_DEB_GPG_KEY

		# Specify nginx package
		NGINX_PACKAGE=nginx-full

		# Dotdeb nginx repository doesn't support spdy
		sed -i "s/ spdy//;" /usr/share/easyengine/nginx/22222
	fi
}
