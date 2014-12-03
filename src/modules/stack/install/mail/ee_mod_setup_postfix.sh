# Setup Postfix

function ee_mod_setup_postfix()
{

	EE_EMAIL=$($EE_CONFIG_GET wordpress.email)
	if [[ $EE_EMAIL = "" ]]; then
		EE_EMAIL=$(git config user.email)
	fi

	EE_HOSTNAME=$(hostname -f)

	#We previously not used this package. So, if some one don't have Postfix-MySQL installed,
	#Postfix will not work
	ee_lib_echo "Installing Postfix-MySQL, please wait..."
	$EE_APT_GET install postfix-mysql \
	|| ee_lib_error "Unable to install Postfix-MySQL, exit status = " $?

	ee_lib_echo "Setting up Postfix, please wait..."
	#Configure Master.cf
	sed -i 's/#submission/submission/' /etc/postfix/master.cf &&
	sed -i 's/#smtps/smtps/' /etc/postfix/master.cf \
	|| ee_lib_error "Unable to setup details in master.cf file, exit status = " $?

	# Handle SMTP authentication using Dovecot"
	# On Debian6 following command not work ( Postfix < 2.8 )
	# postconf "smtpd_sasl_type = dovecot"
  # The -e option is no longer needed with Postfix version 2.8 and later.

	postconf -e "smtpd_sasl_type = dovecot"
	postconf -e "smtpd_sasl_path = private/auth"
	postconf -e "smtpd_sasl_auth_enable = yes"

	postconf -e "smtpd_relay_restrictions = permit_sasl_authenticated, permit_mynetworks, reject_unauth_destination"

	# other destination domains should be handled using virtual domains
	postconf -e "mydestination = localhost"

	# using Dovecot's LMTP for mail delivery and giving it path to store mail
	postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"

	# virtual mailbox setups
	postconf -e "virtual_uid_maps = static:5000"
	postconf -e "virtual_gid_maps = static:5000"
	postconf -e "virtual_mailbox_domains = mysql:/etc/postfix/mysql/virtual_domains_maps.cf"
	postconf -e "virtual_mailbox_maps = mysql:/etc/postfix/mysql/virtual_mailbox_maps.cf"
	postconf -e "virtual_alias_maps = mysql:/etc/postfix/mysql/virtual_alias_maps.cf"
	#postconf "message_size_limit = 20971520"


	# Setting up Postfix MySQL configuration
	mkdir -p /etc/postfix/mysql
	cp -av /usr/share/easyengine/mail/virtual_alias_maps.cf /etc/postfix/mysql/virtual_alias_maps.cf &>> $EE_COMMAND_LOG && \
	cp -av /usr/share/easyengine/mail/virtual_domains_maps.cf /etc/postfix/mysql/virtual_domains_maps.cf &>> $EE_COMMAND_LOG && \
	cp -av /usr/share/easyengine/mail/virtual_mailbox_maps.cf /etc/postfix/mysql/virtual_mailbox_maps.cf &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to copy Postfix MySQL configuration files, exit status = " $?

	# Configure self signed SSL for Postfix
	ee_lib_echo "Generating self signed certificate for Postfix, please wait..."
	openssl req -new -x509 -days 3650 -nodes -subj /commonName=${EE_HOSTNAME}/emailAddress=${EE_EMAIL} -out /etc/ssl/certs/postfix.pem -keyout /etc/ssl/private/postfix.pem &>> $EE_COMMAND_LOG
	chmod 0600 /etc/ssl/private/postfix.pem

	postconf -e smtpd_tls_cert_file=/etc/ssl/certs/postfix.pem
	postconf -e smtpd_tls_key_file=/etc/ssl/private/postfix.pem

}
