# Create Database for WordPress site

function ee_mod_create_database()
{
	local ee_replace_dot=$(echo $EE_DOMAIN | tr '.' '_')
	# Check use default database user or custom database user
	EE_WP_DB_RANDOM_PASSWORD=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)

	# Setup MySQL database
	if [ $($EE_CONFIG_GET mysql.db-name) == "true" ];then
		read -p "Enter the MySQL database name [$ee_replace_dot]: " ee_database_name
	fi

	if [ $ee_database_name = "" ]; then
		ee_database_name=$ee_replace_dot
	fi

	mysql -e "create database \`${ee_database_name}\`" \
	|| ee_lib_error "Unable to create $ee_database_name database"

	# Setup MySQL user
	if [ $($EE_CONFIG_GET mysql.db-user) == "true" ];then
		read -p "Enter the MySQL database username [$ee_replace_dot]: " ee_database_user		
		read -sp "Enter The MySQL Database Password [$EE_WP_DB_RANDOM_PASSWORD]: " EE_WP_DB_PASS
	fi
	elif 
		ee_database_user=$ee_replace_dot
		# Fix MySQL USER ERROR 1470 (HY000)
		EE_WP_DB_PASS=$EE_WP_DB_RANDOM_PASSWORD
	fi

	local ee_mysql_user_16=$(echo -n $ee_database_user | wc -c)

	if [[ $ee_mysql_user_16 -gt 16 ]]; then
		echo "MySQL database username $ee_database_user = $ee_mysql_user_16" &>> $EE_COMMAND_LOG
		ee_lib_echo "Auto fix MySQL username to the 16 characters"
		local ee_random_character=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 10 | head -n1)
		ee_database_user=$(echo $ee_mysql_user_16 | cut -c1-16 | sed "s/.\{10\}$/$ee_random_character/")
	fi

	# create separate user & grant permission
	echo -e "ee_database_name = $ee_database_name \nWPDBUSER = $ee_database_user \nWPDBPASS = $EE_WP_DB_PASS" &>> $EE_COMMAND_LOG
	mysql -e "create user '$ee_database_user'@'$MYSQLHOST' identified by '$EE_WP_DB_PASS'"
	mysql -e "grant all privileges on \`$ee_database_name\`.* to '$ee_database_user'@'$MYSQLHOST'"
	mysql -e "flush privileges"
}
