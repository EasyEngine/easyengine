# Delete webroot

function ee_mod_delete_webroot()
{

	if [ "$1" = "--no-prompt" ];then
			# Delete webroot without any prompt
			local ee_prompt="y"
		else
			# Ask user to confirm
			read -p "Are you sure to remove $EE_DOMAIN webroot (y/n): " ee_prompt
		fi

	if [ "$ee_prompt" = "y" ]; then
		# Delete $EE_DOMAIN webroot
		rm -rf /var/www/$EE_DOMAIN \
		|| ee_lib_error "Unable to remove $EE_DOMAIN webroot, exit status = " $?
	else
		# Deny message
		ee_lib_echo_fail "User denied to remove $EE_DOMAIN webroot"
	fi
}
