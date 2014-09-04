# Open website in default editor

function ee_mod_site_edit()
{
	grep vim ~/.selected_editor &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		sensible-editor /etc/nginx/sites-available/$EE_DOMAIN $1 2> /dev/null
	else
		sensible-editor /etc/nginx/sites-available/$EE_DOMAIN $1
	fi
}
