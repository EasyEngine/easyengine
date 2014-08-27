# Remove MailScan package

function ee_mod_remove_mysql()
{
	ee_lib_echo "$EE_SECOND Amavis, SpamAssassin and ClamAV package, please wait..."
	$EE_APT_GET $EE_SECOND amavisd-new spamassassin clamav clamav-daemon arj zoo nomarch cpio lzop cabextract apt-listchanges libauthen-sasl-perl  libdbi-perl libmail-dkim-perl p7zip rpm unrar-free libsnmp-perl \
	|| ee_lib_error "Unable to $EE_SECOND Amavis, SpamAssassin and ClamAV,, exit status = " $?

}
