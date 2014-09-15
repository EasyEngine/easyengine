#CD to Webroot

function ee_site_cd()
{
	cd $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g') \
	|| ee_lib_error "Unable to change directory for $EE_DOMAIN, exit status = " $?
	exec bash
}