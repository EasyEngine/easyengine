# Install Dovecot package

function ee_mod_install_dovecot()
{
	# Install Dovecot
	dpkg -l | grep nginx > /dev/null \
	&& dpkg -l | grep php5-fpm > /dev/null \
	&& dpkg -l | grep mysql > /dev/null \
	&&  dpkg -l | grep postfix > /dev/null
	if [ $? -ne 0 ];then
		ee_lib_error "Failed to find pre dependencies. Please install Nginx, PHP5, MySQL and Postfix using command ee stack install, exit status " 1
	fi

	dpkg -l | grep dovecot-core > /dev/null
	if [ $? -eq 0 ];then
		ee_lib_error "Found installed mail server, Please remove it before installation, exit status=" 1
	fi

	ee_lib_echo "Installing Dovecot, please wait..."
	debconf-set-selections <<< "dovecot-core dovecot-core/create-ssl-cert boolean yes"
	debconf-set-selections <<< "dovecot-core dovecot-core/ssl-cert-name string $(hostname -f)"
	$EE_APT_GET install dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd dovecot-mysql dovecot-sieve dovecot-managesieved \
	|| ee_lib_error "Unable to install Dovecot, exit status = " $?

}
