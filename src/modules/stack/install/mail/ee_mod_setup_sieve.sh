# Setup Sieve rules

function ee_mod_setup_sieve()
{
	EE_EMAIL=$($EE_CONFIG_GET wordpress.email)
	if [[ $EE_EMAIL = "" ]]; then
		EE_EMAIL=$(git config user.email)
	fi

	ee_lib_echo "Setting up Sieve rules, please wait..."

	# Enable sieve plugin support for dovecot-lmtp
	sed -i "s'  #mail_plugins = \$mail_plugins'  postmaster_address =$EE_EMAIL \n  mail_plugins = \$mail_plugins sieve'" /etc/dovecot/conf.d/20-lmtp.conf \
	|| ee_lib_error "Unable to add sieve plugin support for dovecot-lmtp, exit status = " $?

	# Sieve dovecot-pluign configuration
	sed -i "s'sieve = ~/.dovecot.sieve'sieve = ~/.dovecot.sieve\n  sieve_global_path = /var/lib/dovecot/sieve/default.sieve'" /etc/dovecot/conf.d/90-sieve.conf && \
	sed -i "s'#sieve_global_dir ='sieve_global_dir = /var/lib/dovecot/sieve/'" /etc/dovecot/conf.d/90-sieve.conf \
	|| ee_lib_error "Unable to setup Sieve dovecot-pluign, exit status = " $?

	# Create global Sieve rules file
	mkdir -p /var/lib/dovecot/sieve/
	cp /usr/share/easyengine/mail/default.sieve /var/lib/dovecot/sieve/default.sieve
	chown -R vmail:vmail /var/lib/dovecot

	# Compile Sieve rules
	sievec /var/lib/dovecot/sieve/default.sieve \
	|| ee_lib_error "Unable to compile Sieve rules, exit status = " $?

	# Configure Roundcube 
	sed -i "s:\$config\['plugins'\] = array(:\$config\['plugins'\] = array(\n    'sieverules',:" /var/www/roundcubemail/htdocs/config/config.inc.php \
	|| ee_lib_error "Unable to configure Sieve Roundcube plugin, exit status = " $?

	echo "\$config['sieverules_port'] = 4190;" >> /var/www/roundcubemail/htdocs/config/config.inc.php

}
