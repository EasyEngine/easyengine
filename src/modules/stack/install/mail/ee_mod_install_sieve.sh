# Install Sieve package

function ee_mod_install_sieve()
{
	# Install Sieve
	ee_lib_echo "Installing Sieve, please wait..."
	$EE_APT_GET install dovecot-sieve dovecot-managesieved \
	|| ee_lib_error "Unable to install Sieve, exit status = " $?
}
