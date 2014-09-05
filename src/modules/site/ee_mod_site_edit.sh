# Open website in default editor

function ee_mod_site_edit()
{
	# Redirect VIM warning to /dev/null
	sensible-editor --help | head -n1 | grep VIM &>> $EE_COMMAND_LOG
	if [ $? -eq 0 ]; then
		sensible-editor /etc/nginx/sites-available/$EE_DOMAIN $1 2> /dev/null
	else
		sensible-editor /etc/nginx/sites-available/$EE_DOMAIN $1
	fi
}
