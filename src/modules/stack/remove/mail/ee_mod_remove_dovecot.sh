# Remove Dovecot package

function ee_mod_remove_dovecot()
{
	ee_lib_echo "$EE_SECOND Dovecot package, please wait..."
	$EE_APT_GET $EE_SECOND dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql \
	|| ee_lib_error "Unable to $EE_SECOND Dovecot, exit status = " $?

	userdel -rf vmail || ee_lib_error "Unable to Remove user vmail, exit status = " $?
}
