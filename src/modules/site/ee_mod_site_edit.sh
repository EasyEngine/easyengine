# Open website in default editor

function ee_mod_site_edit()
{
	sensible-editor /etc/nginx/sites-available/$EE_DOMAIN $EE_VIM 2> /dev/null
}
