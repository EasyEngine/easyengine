# Setup Dovecot

function ee_mod_setup_dovecot()
{
	ee_lib_echo "Configuring Dovecot, please wait..."
	# Adding mail user with GID 5000 and UID 5000
	groupadd -g 5000 vmail
	useradd -g vmail -u 5000 vmail -d /var/vmail -m

	# Configuring dovecot.conf
	sed -i "s/*.protocol/*.protocol\\nprotocols = imap pop3 lmtp sieve/" /etc/dovecot/dovecot.conf

	# Configuring 10-mail.conf
	sed -i "s/mail_location = mbox:~\/mail:INBOX=\/var\/mail\/%u/mail_location = maildir:\/var\/vmail\/%d\/%n/" /etc/dovecot/conf.d/10-mail.conf
	
	# Configuring 10-auth.conf
	sed -i "s/auth_mechanisms = plain/auth_mechanisms = plain login/" /etc/dovecot/conf.d/10-auth.conf
	sed -i "s/\!include auth-system.conf.ext/#\!include auth-system.conf.ext/" /etc/dovecot/conf.d/10-auth.conf
	sed -i "s/#\!include auth-sql.conf.ext/\!include auth-sql.conf.ext/" /etc/dovecot/conf.d/10-auth.conf

	# Configuring dovecot-sql.conf.ext
	cat /usr/share/easyengine/mail/dovecot-sql.conf.ext >> /etc/dovecot/dovecot-sql.conf.ext

	# Configuring auth-sql.conf.ext
	sed -i "s/#userdb {/userdb {/" /etc/dovecot/conf.d/auth-sql.conf.ext
	sed -i "s/#  driver = prefetch/  driver = prefetch\n}/" /etc/dovecot/conf.d/auth-sql.conf.ext


	# Configuring 10-master.conf
	cp -av /etc/dovecot/conf.d/10-master.conf /etc/dovecot/conf.d/10-master.conf.bak
	cp -av /usr/share/easyengine/mail/10-master.conf /etc/dovecot/conf.d/10-master.conf

	# Change Dovecot log location
	sed -i "s/#log_path = syslog/log_path = \/var\/log\/dovecot.log/" /etc/dovecot/conf.d/10-logging.conf
}
