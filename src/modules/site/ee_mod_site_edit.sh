# Open website in default editor

function ee_mod_site_edit()
{
	sensible-editor /etc/nginx/sites-available/$EE_DOMAIN $1 2> /dev/null
}
