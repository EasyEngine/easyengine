# Install Dovecot package

function ee_mod_install_dovecot()
{
	# Add Dovecot repo for Debian 6
	ee_mod_repo_dovecot
	ee_lib_apt_get_update

	# Install Dovecot
	ee_lib_echo "Installing Dovecot, please wait..."
	debconf-set-selections <<< "dovecot-core dovecot-core/create-ssl-cert boolean yes"
	debconf-set-selections <<< "dovecot-core dovecot-core/ssl-cert-name string $(hostname -f)"

	# 2>&1 is needed as config file is written in STDEER
	# Debian 6 doesn't provide Dovecot 2.x
	if [ "$EE_DEBIAN_VERSION" == "squeeze" ]; then
		$EE_APT_GET -t squeeze-backports install dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql dovecot-sieve dovecot-managesieved 2>&1 \
		|| ee_lib_error "Unable to install Dovecot, exit status = " $?
	else
		$EE_APT_GET install dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql dovecot-sieve dovecot-managesieved 2>&1 \
		|| ee_lib_error "Unable to install Dovecot, exit status = " $?
	fi

}
