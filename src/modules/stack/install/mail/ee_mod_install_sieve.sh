# Install Sieve package

function ee_mod_install_sieve()
{
	# Install Sieve
	ee_lib_echo "Installing Sieve, please wait..."
	$EE_APT_GET install amavisd-new spamassassin clamav clamav-daemon arj zoo nomarch cpio lzop cabextract apt-listchanges libauthen-sasl-perl  libdbi-perl libmail-dkim-perl p7zip rpm unrar-free libsnmp-perl \
	|| ee_lib_error "Unable to install Sieve, exit status = " $?
}
