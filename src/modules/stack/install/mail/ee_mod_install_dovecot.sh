# Install Dovecot package

function ee_mod_install_dovecot()
{
	# Install Dovecot
	ee_lib_echo "Installing Dovecot, please wait..."
	debconf-set-selections <<< "dovecot-core dovecot-core/create-ssl-cert boolean yes"
	debconf-set-selections <<< "dovecot-core dovecot-core/ssl-cert-name string $(hostname -f)"
	$EE_APT_GET install dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql dovecot-sieve dovecot-managesieved \
	|| ee_lib_error "Unable to install Dovecot, exit status = " $?

}
