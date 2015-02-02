# Setup Percona repository

function ee_mod_repo_mysql()
{
	# Add Percona repository
	ee_lib_echo "Adding Percona repository, please wait..."
	echo "deb http://repo.percona.com/apt $(lsb_release -sc) main" > /etc/apt/sources.list.d/percona-$(lsb_release -sc).list \
	|| ee_lib_error "Unable to add Percona repository, exit status = " $?

	# Fetch and install Percona GnuPG key
	gpg --keyserver  hkp://keyserver.ubuntu.com/ --recv-keys 1C4CBDCDCD2EFD2A &>> $EE_COMMAND_LOG && \
	gpg -a --export CD2EFD2A | apt-key add - &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to add Percona GnuPG key, exit status = " $?
}
