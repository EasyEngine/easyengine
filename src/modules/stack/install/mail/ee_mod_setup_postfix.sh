# Setup Postfix

function ee_mod_setup_postfix()
{

	EE_EMAIL=$($EE_CONFIG_GET wordpress.email)
	if [[ $EE_EMAIL = "" ]]; then
		EE_EMAIL=$(git config user.email)
	fi

	EE_HOSTNAME=$(hostname -f)
	ee_lib_echo "Setting up Postfix, please wait..."
	#Configure Master.cf
	sed -i 's/#submission/submission/' /etc/postfix/master.cf &&
	sed -i 's/#smtps/smtps/' /etc/postfix/master.cf \
	|| ee_lib_error "Unable to setup details in master.cf file, exit status = " $?	

	#Configure main.cf
	#postconf "#smtpd_tls_session_cache_database = btree:${data_directory}/smtpd_scache"
	#postconf "#smtp_tls_session_cache_database = btree:${data_directory}/smtp_scache"
	#postconf "#smtpd_tls_cert_file=/etc/ssl/certs/dovecot.pem"
	#postconf "#smtpd_use_tls=yes"
	#postconf "#smtpd_tls_auth_only = yes"

	#Handle SMTP authentication using Dovecot"
	postconf "smtpd_sasl_type = dovecot"
	postconf "smtpd_sasl_path = private/auth"
	postconf "smtpd_sasl_auth_enable = yes"

	postconf "smtpd_relay_restrictions = permit_sasl_authenticated, permit_mynetworks, reject_unauth_destination"

	# other destination domains should be handled using virtual domains 
	postconf "mydestination = localhost"

	# using Dovecot's LMTP for mail delivery and giving it path to store mail
	postconf "virtual_transport = lmtp:unix:private/dovecot-lmtp"

	# virtual mailbox setups
	postconf "virtual_uid_maps = static:5000"
	postconf "virtual_gid_maps = static:5000"
	postconf "virtual_mailbox_domains = mysql:/etc/postfix/mysql/virtual_domains_maps.cf"
	postconf "virtual_mailbox_maps = mysql:/etc/postfix/mysql/virtual_mailbox_maps.cf"
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
	postconf -e smtpd_tls_cert_file=/etc/ssl/certs/postfix.pem
	postconf -e smtpd_tls_key_file=/etc/ssl/private/postfix.pem

}
