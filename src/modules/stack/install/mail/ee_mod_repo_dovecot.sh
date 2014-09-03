# Setup nginx repository

function ee_mod_repo_dovecot()
{
	if [ "$EE_DEBIAN_VERSION" == "squeeze" ];then

		# Add Dovecot repository
		# Ref:http://wiki2.dovecot.org/PrebuiltBinaries

		ee_lib_echo "Adding Dovecot repository, please wait..."
		echo "deb http://xi.rename-it.nl/debian/ oldstable-auto/dovecot-2.2 main" > /etc/apt/sources.list.d/dovecot-$(lsb_release -sc).list \
		|| ee_lib_error "Unable to add Dovecot repository, exit status = " $?

		# Fetch and install Dovecot GnuPG key
		wget -O - http://xi.rename-it.nl/debian/archive.key | apt-key add - \
		|| ee_lib_error "Unable to fetch Dovecot GnuPG key, exit status = " $?

	fi
}
