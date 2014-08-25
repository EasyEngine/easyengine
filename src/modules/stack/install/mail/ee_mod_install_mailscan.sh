# Install Amavis package

function ee_mod_install_mailscan()
{
	# Install Amavis
	ee_lib_echo "Installing Amavis, SpamAssassin and ClamAV, please wait..."
	$EE_APT_GET install amavisd-new spamassassin clamav clamav-daemon arj zoo nomarch cpio lzop cabextract apt-listchanges libauthen-sasl-perl  libdbi-perl libmail-dkim-perl p7zip rpm unrar-free libsnmp-perl \
	|| ee_lib_error "Unable to install Amavis, SpamAssassin and ClamAV, exit status = " $?
}
