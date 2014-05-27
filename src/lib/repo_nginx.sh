# Setup nginx repository

function repo_nginx()
{
	if [ "$LINUX_DISTRO" == "Ubuntu" ];	then

		# Add rtCamp nginx launchpad repository
		echo_blue "Adding rtCamp nginx launchpad repository, please wait..."
		add-apt-repository -y ppa:rtcamp/nginx &>> $EE_LOG \
		|| ee_error "Unable to add rtCamp nginx launchpad repository, exit status = " $?

		# Specify nginx package
		NGINX_PACKAGE=nginx-custom

	elif [ "$LINUX_DISTRO" == "Debian" ]; then

		# Add dotdeb nginx repository
		echo_blue "Adding dotdeb nginx repository, please wait..."
		echo "deb http://packages.dotdeb.org $(lsb_release -c | awk '{print($2)}') all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -c | awk '{print($2)}').list \
		|| ee_error "Unable to add dotdeb nginx repository, exit status = " $?

		# Fetch and install dotdeb GnuPG key
		dot_deb_gpg_key

		# Specify nginx package
		NGINX_PACKAGE=nginx-full

		# Dotdeb nginx repository doesn't support spdy
		sed -i "s/ spdy//;" /usr/share/easyengine/nginx/22222

	fi
}
