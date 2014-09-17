# Setup Percona repository

function ee_mod_repo_mysql()
{
	# Add Percona repository
	ee_lib_echo "Adding Percona repository, please wait..."
	echo "deb http://repo.percona.com/apt $(lsb_release -sc) main" > /etc/apt/sources.list.d/percona-$(lsb_release -sc).list \
	|| ee_lib_error "Unable to add Percona repository, exit status = " $?

	# Fetch and install Percona GnuPG key
	gpg --keyserver  hkp://keys.gnupg.net --recv-keys 1C4CBDCDCD2EFD2A && \
	gpg -a --export CD2EFD2A | sudo apt-key add - \
	|| ee_lib_error "Unable to add Percona GnuPG key, exit status = " $?
}
