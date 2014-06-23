# Delete NGINX configuration file

function ee_mod_delete_nginxconf()
{

	if [ "$1" = "--no-prompt" ];then
			# Delete NGINX configuration without any prompt
			local ee_prompt="y"
		else
			# Fix read prompt
			stty echo
			# Ask user to confirm
			read -p "Are you sure to remove $EE_DOMAIN NGINX configuration (y/n): " ee_prompt
		fi

	if [ "$ee_prompt" = "y" ]; then
		# Delete $EE_DOMAIN NGINX configuration
		rm -rf /etc/nginx/sites-available/$EE_DOMAIN /etc/nginx/sites-enabled/$EE_DOMAIN \
		|| ee_lib_error "Unable to remove $EE_DOMAIN NGINX configuration, exit status = " $?
	else
		# Deny message
		ee_lib_echo_fail "User denied to remove $EE_DOMAIN NGINX configuration"
	fi
}
