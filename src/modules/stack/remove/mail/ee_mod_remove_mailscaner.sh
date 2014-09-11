# Remove MailScan package

function ee_mod_remove_mailscaner()
{
	
	# Remove Amavis configuration from Postfix configuration
	# Better approach is: postconf -X "content_filter", But available for Postfix 2.11 (latest)
	sed -i '/content_filter/d' /etc/postfix/main.cf
	sed -i '/content_filter/d' /etc/postfix/master.cf
	sed -i '/receive_override_options/d' /etc/postfix/master.cf
	sed -i '/smtp-amavis/,$d' /etc/postfix/master.cf
	
	#Remove/Purge mailscan packages
	ee_lib_echo "$EE_SECOND Amavis, SpamAssassin and ClamAV package, please wait..."
	$EE_APT_GET $EE_SECOND amavisd-new spamassassin clamav clamav-daemon arj zoo nomarch lzop cabextract p7zip rpm unrar-free \
	|| ee_lib_error "Unable to $EE_SECOND Amavis, SpamAssassin and ClamAV,, exit status = " $?

}
