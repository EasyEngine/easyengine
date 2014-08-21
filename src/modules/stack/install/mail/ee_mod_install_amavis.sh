# Install Amavis package

function ee_mod_install_amavis()
{
	# Install Amavis
	ee_lib_echo "Installing Amavis and ClamAV, please wait..."
	$EE_APT_GET install amavisd-new spamassassin clamav clamav-daemon arj zoo nomarch cpio lzop cabextract apt-listchanges libauthen-sasl-perl  libdbi-perl libmail-dkim-perl p7zip rpm unrar-free libsnmp-perl \
	|| ee_lib_error "Unable to install Amavis and ClamAV, exit status = " $?
}
