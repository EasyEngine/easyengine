# Setup Amavis

function ee_mod_setup_amavis()
{
	# Confiure Amavis
	sed -i "s'#@'@'" /etc/amavis/conf.d/15-content_filter_mode
	sed -i "s'#   '   '" /etc/amavis/conf.d/15-content_filter_mode

	# Add mail filtering rules
	sed -i "s/use strict;/use strict;\n\$sa_spam_subject_tag = undef;\n\$spam_quarantine_to  = undef;\n\$sa_tag_level_deflt  = undef;\n\n# Prevent spams from automatically rejected by mail-server\n\$final_spam_destiny  = D_PASS;\n# We need to provide list of domains for which filtering need to be done\n@lookup_sql_dsn = (\n    ['DBI:mysql:database=vimbadmin;host=127.0.0.1;port=3306',\n     'vimbadmin',\n     'password']);\n\n\$sql_select_policy = 'SELECT domain FROM domain WHERE CONCAT("@",domain) IN (%k)';/" /etc/amavis/conf.d/50-user

	# Configure Postfix to use Amavis
	# For postfix main.cf
	postconf -e "content_filter = smtp-amavis:[127.0.0.1]:10024"

	# For postfix master.cf
	sed -i "s/1       pickup/1       pickup\n        -o content_filter=\n        -o receive_override_options=no_header_body_checks/" /etc/postfix/master.cf
	cat /usr/share/easyengine/mail/amavis-master.cf >> /etc/postfix/master.cf
	
}
