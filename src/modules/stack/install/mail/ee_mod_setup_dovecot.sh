# Setup Dovecot

function ee_mod_setup_dovecot()
{
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
	cp -v /usr/share/easyengine/mail/dovecot-sql.conf.ext /etc/dovecot/dovecot-sql.conf.ext \
	|| ee_lib_error "Unable to copy dovecot-sql.conf.ext, exit status = " $?

	# Configuring auth-sql.conf.ext
	sed -i "1s/#userdb {/userdb {/" /etc/dovecot/conf.d/auth-sql.conf.ext && \
	sed -i "s/#  driver = prefetch/  driver = prefetch\n}/" /etc/dovecot/conf.d/auth-sql.conf.ext \
	|| ee_lib_error "Unable to setup auth-sql.conf.ext, exit status = " $?


	# Configuring 10-master.conf
	cp -v /etc/dovecot/conf.d/10-master.conf /etc/dovecot/conf.d/10-master.conf.bak 
	cp -v /usr/share/easyengine/mail/10-master.conf /etc/dovecot/conf.d/10-master.conf \
	|| ee_lib_error "Unable to setup 10-master.conf, exit status = " $?

	# Change Dovecot log location
	sed -i "s/#log_path = syslog/log_path = \/var\/log\/dovecot.log/" /etc/dovecot/conf.d/10-logging.conf \
	|| ee_lib_error "Unable to setup Dovecot log_path, exit status = " $?
}
