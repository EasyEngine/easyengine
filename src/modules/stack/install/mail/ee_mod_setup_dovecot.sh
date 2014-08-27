# Setup Dovecot

function ee_mod_setup_dovecot()
{

	EE_EMAIL=$($EE_CONFIG_GET wordpress.email)
	if [[ $EE_EMAIL = "" ]]; then
		EE_EMAIL=$(git config user.email)
	fi

	EE_HOSTNAME=$(hostname -f)

	ee_lib_echo "Setting up Dovecot, please wait..."
	# Adding mail user with GID 5000 and UID 5000
	groupadd -g 5000 vmail &&	useradd -g vmail -u 5000 vmail -d /var/vmail -m \
	|| ee_lib_error "Unable to setup vmail user/group = " $?

	# Configuring dovecot.conf
	sed -i "s/*.protocol/*.protocol\\nprotocols = imap pop3 lmtp sieve/" /etc/dovecot/dovecot.conf \
	|| ee_lib_error "Unable to configure Dovecot protocol, exit status = " $?

	# Configuring 10-mail.conf
	sed -i "s/mail_location = mbox:~\/mail:INBOX=\/var\/mail\/%u/mail_location = maildir:\/var\/vmail\/%d\/%n/" /etc/dovecot/conf.d/10-mail.conf \
	|| ee_lib_error "Unable to configure Dovecot mail_location, exit status = " $?
	
	# Configuring 10-auth.conf
	sed -i "s/#disable_plaintext_auth = yes/disable_plaintext_auth = no/" /etc/dovecot/conf.d/10-auth.conf && \
	sed -i "s/auth_mechanisms = plain/auth_mechanisms = plain login/" /etc/dovecot/conf.d/10-auth.conf && \
	sed -i "s/\!include auth-system.conf.ext/#\!include auth-system.conf.ext/" /etc/dovecot/conf.d/10-auth.conf && \
	sed -i "s/#\!include auth-sql.conf.ext/\!include auth-sql.conf.ext/" /etc/dovecot/conf.d/10-auth.conf \
	|| ee_lib_error "Unable to setup 10-auth.conf file, exit status = " $?

	# Configuring dovecot-sql.conf.ext
	cp -v /usr/share/easyengine/mail/dovecot-sql.conf.ext /etc/dovecot/dovecot-sql.conf.ext &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to copy dovecot-sql.conf.ext, exit status = " $?

	# Configuring auth-sql.conf.ext
	sed -i "s/#  driver = prefetch/userdb {\n  driver = prefetch\n}/" /etc/dovecot/conf.d/auth-sql.conf.ext \
	|| ee_lib_error "Unable to setup auth-sql.conf.ext, exit status = " $?


	# Configuring 10-master.conf
	cp -v /etc/dovecot/conf.d/10-master.conf /etc/dovecot/conf.d/10-master.conf.bak &>> $EE_COMMAND_LOG
	cp -v /usr/share/easyengine/mail/10-master.conf /etc/dovecot/conf.d/10-master.conf &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to setup 10-master.conf, exit status = " $?

	# Change Dovecot log location
	sed -i "s/#log_path = syslog/log_path = \/var\/log\/dovecot.log/" /etc/dovecot/conf.d/10-logging.conf \
	|| ee_lib_error "Unable to setup Dovecot log_path, exit status = " $?

	# Configure self signed SSL for Dovecot
	ee_lib_echo "Generating self signed certificate for Dovecot, please wait..."
	openssl req -new -x509 -days 3650 -nodes -subj /commonName=${EE_HOSTNAME}/emailAddress=${EE_EMAIL} -out /etc/ssl/certs/dovecot.pem -keyout /etc/ssl/private/dovecot.pem &>> $EE_COMMAND_LOG
	chmod 0600 /etc/ssl/private/dovecot.pem

	# Setting up certificate in file
	sed -i "s'/etc/dovecot/dovecot.pem'/etc/ssl/certs/dovecot.pem'" /etc/dovecot/conf.d/10-ssl.conf \
	&& sed -i "s'/etc/dovecot/private/dovecot.pem'/etc/ssl/private/dovecot.pem'" /etc/dovecot/conf.d/10-ssl.conf \
	|| ee_lib_error "Unable to setup Dovecot SSL certificate path, exit status = " $?

	# Setting Dovecot init.d script
	cp -v /usr/share/easyengine/mail/dovecot /etc/init.d/dovecot &>> $EE_COMMAND_LOG

	# Add autocreate plugin
	sed -i "s'#mail_plugins = \$mail_plugins'mail_plugins = \$mail_plugins autocreate'" /etc/dovecot/conf.d/20-imap.conf \
	|| ee_lib_error "Unable to setup Dovecot autocreate plugin, exit status = " $?
	cat /usr/share/easyengine/mail/autocreate >> /etc/dovecot/conf.d/20-imap.conf

}
