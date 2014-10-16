# Delete MySQL database

function ee_mod_delete_database()
{
	# HTML & PHP website doesn't have database
	head -n1 /etc/nginx/sites-available/$EE_DOMAIN | egrep -e 'HTML|PHP' &>> $EE_COMMAND_LOG
	if [ $? -ne 0 ]; then
		# Database details
		local ee_db_name=$(grep DB_NAME /var/www/$EE_DOMAIN/*-config.php | cut -d"'" -f4)
		local ee_db_user=$(grep DB_USER /var/www/$EE_DOMAIN/*-config.php | cut -d"'" -f4)
		local ee_db_pass=$(grep DB_PASS /var/www/$EE_DOMAIN/*-config.php | cut -d"'" -f4)
		local ee_db_host=$(grep DB_HOST /var/www/$EE_DOMAIN/*-config.php | cut -d"'" -f4)
		ee_lib_echo_escape " DB_NAME = $ee_db_name \n DB_USER = $ee_db_user \n DB_HOST = $ee_db_host \n GRANT_HOST = $EE_MYSQL_GRANT_HOST"

		if [ "$1" = "--no-prompt" ];then
			# Delete database without any prompt
			local ee_prompt="y"
		else
			# Fix read prompt
			stty echo
			# Ask user to confirm
			read -p "Are you sure to drop $ee_db_name database (y/n): " ee_prompt
		fi

		if [ "$ee_prompt" = "y" ]; then
			# Drop database
			mysql -e "drop database \`$ee_db_name\`" \
			|| ee_lib_error "Unable to drop $ee_db_name database, exit status = " $?

			# Never drop root user
			if [ "$ee_db_user" != "root" ]; then
				# Drop database user
				mysql -e "drop user '$ee_db_user'@'$EE_MYSQL_GRANT_HOST'" \
				|| ee_lib_error "Unable to drop database user $ee_db_user, exit status = " $?
				# Flush privileges
				mysql -e "flush privileges" \
				|| ee_lib_error "Unable to flush MySQL privileges, exit status = " $?
			fi
		else
			# Deny message
			ee_lib_echo_fail "User denied to drop $ee_db_name database"
		fi
	fi
}
