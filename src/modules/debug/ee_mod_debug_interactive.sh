# Enables debug in interactive mode
# When ee debug module run with -i flag
# This function is called display output on screen until
# User press CTRL+C (which call ee_mod_debug_stop function)

function ee_mod_debug_interactive()
{
	if [ -z "$EE_DOMAIN" ]; then
		tail -f /var/log/nginx/*.error.log /var/log/php5/*.log /var/log/mysql/*.log
	else
		tail -f /var/log/nginx/*.error.log /var/log/php5/*.log /var/log/mysql/*.log /var/www/$EE_DOMAIN/htdocs/wp-content/debug.log
	fi
}
