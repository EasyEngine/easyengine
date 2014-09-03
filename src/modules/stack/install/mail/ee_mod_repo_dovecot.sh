# Setup nginx repository

function ee_mod_repo_dovecot()
{
	if [ "$EE_DEBIAN_VERSION" == "squeeze" ];then

		# Add Dovecot repository
		ee_lib_echo "Adding Dovecot repository, please wait..."
		echo "deb http://http.debian.net/debian-backports squeeze-backports main" > /etc/apt/sources.list.d/dovecot-$(lsb_release -sc).list \
		|| ee_lib_error "Unable to add Dovecot repository, exit status = " $?

	fi
}
