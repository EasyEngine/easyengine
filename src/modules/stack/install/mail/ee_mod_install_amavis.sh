# Install Amavis package

function ee_mod_install_dovecot()
{
	# Install Amavis
	ee_lib_echo "Installing Amavis, please wait..."
	$EE_APT_GET amavisd-new spamassassin clamav clamav-daemon arj zoo nomarch cpio lzop cabextract apt-listchanges libauthen-sasl-perl  libdbi-perl libmail-dkim-perl p7zip rpm unrar-free libsnmp-perl \
	|| ee_lib_error "Unable to install Amavis, exit status = " $?
}
