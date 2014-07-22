# Setup Postfix

function ee_mod_setup_dovecot()
{
	groupadd -g 5000 vmail
	useradd -g vmail -u 5000 vmail -d /var/vmail -m
}
