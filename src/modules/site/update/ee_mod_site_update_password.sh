# Update WordPress user password

ee_mod_site_update_password()
{
	local ee_wp_user ee_wp_pass
	
	cd $(grep root /etc/nginx/sites-available/$EE_DOMAIN | awk '{ print $2 }' | sed 's/;//g') \
	|| ee_lib_error "Unable to change directory for $EE_DOMAIN, exit status = " $?
	
	wp --allow-root core version &>> /dev/null \
	|| ee_lib_error "Error: $EE_DOMAIN does not seem to be a WordPress install, exit status = " $?
	
	if [ $? -eq 0 ]; then
		read -p "Provide WordPress user name [admin]: " ee_wp_user
		
		# If user enter ? mark then show list of WordPress users
		if [ "$ee_wp_user" = "?" ]; then
			ee_lib_echo "List of WordPress users:"
			wp --allow-root user list --fields=user_login | grep -v user_login
			read -p "Provide WordPress user name [admin]: " ee_wp_user
		fi

		if [ "$ee_wp_user" = "" ]; then
			ee_wp_user=admin
		fi

		# Check WordPress user exist or not
		wp --allow-root user list --fields=user_login | grep ${ee_wp_user}$ &>> /dev/null
		if [ $? -eq 0 ]; then
			read -sp "Provide password for $ee_wp_user user: " ee_wp_pass
			echo
			if [[ ${#ee_wp_pass} -ge 8 ]]; then
				wp --allow-root user update "${ee_wp_user}" --user_pass=$ee_wp_pass &>> $EE_COMMAND_LOG
			else
				ee_lib_error "Password Unchanged. Hint : Your password must be 8 characters long, exit status = " $?
			fi
		else
			ee_lib_error "Invalid WordPress user $ee_wp_user for $EE_DOMAIN, exit status = " $?
		fi
	fi
}
