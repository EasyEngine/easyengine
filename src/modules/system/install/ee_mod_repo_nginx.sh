# Setup nginx repository

function ee_mod_repo_nginx()
{
	if [ "$EE_LINUX_DISTRO" == "Ubuntu" ];	then

		# Add rtCamp nginx launchpad repository
		ee_lib_echo "Adding rtCamp nginx launchpad repository, please wait..."
		add-apt-repository -y ppa:rtcamp/nginx &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Unable to add rtCamp nginx launchpad repository, exit status = " $?

	elif [ "$EE_LINUX_DISTRO" == "Debian" ]; then

		# Add Dotdeb nginx repository
		ee_lib_echo "Adding Dotdeb nginx repository, please wait..."
		echo "deb http://packages.dotdeb.org $(lsb_release -c | awk '{print($2)}') all" > /etc/apt/sources.list.d/dotdeb-$(lsb_release -c | awk '{print($2)}').list \
		|| ee_lib_error "Unable to add Dotdeb nginx repository, exit status = " $?

		# Fetch and install dotdeb GnuPG key
		ee_lib_dotdeb

		# Dotdeb nginx repository doesn't support spdy
		sed -i "s/ spdy//;" /usr/share/easyengine/nginx/22222

	fi
}
