#Enables debug in interactive mode

function ee_mod_debug_interactive()
{
	if [ -z "$EE_DEBUG_SITENAME" ]; then
		tail -f /var/log/nginx/*.error.log /var/log/php5/*.log /var/log/mysql/*.log
	else
		tail -f /var/log/nginx/*.error.log /var/log/php5/*.log /var/log/mysql/*.log /var/www/$EE_DOMAIN/htdocs/wp-content/debug.log
	fi
}
