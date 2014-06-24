# Database setup

function ee_mod_setup_database()
{
	# Random characters
	local ee_random=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)

	# Replace dot(.) with underscore(_) in EE_DOMAIN Name
	local ee_replace_dot=$(echo $EE_DOMAIN | tr '.' '_')

	# Default database or custom database
	if [ $($EE_CONFIG_GET mysql.db-name) == "true" ];then
		read -p "Enter the MySQL database name [$ee_replace_dot]: " EE_DB_NAME
	fi

	# If mysql.db-name = false 
	# 		Then it never ask for MySQL database name in this case $EE_DB_NAME is empty
	# If mysql.db-name = true
	#		User enter custom database name then $EE_DB_NAME is not empty & we used provided database name
 	#		If user pressed enter then $EE_DB_NAME is empty

 	if [[ $EE_DB_NAME = "" ]]; then
 		EE_DB_NAME=$ee_replace_dot
 	fi

 	# Default database user or custom user
 	if [ $($EE_CONFIG_GET mysql.db-user) == "true" ]; then
 		read -p "Enter the MySQL database username [$ee_replace_dot]: " EE_DB_USER
 		read -sp "Enter the MySQL database password [$ee_random]: " EE_DB_PASS
 	fi

 	# If mysql.db-user = false 
	# 		Then it never ask for MySQL database user in this case $EE_DB_USER is empty
	# If mysql.db-name = true
	#		User enter custom database user then $EE_DB_USER is not empty & we used provided database user
 	#		If user pressed enter then $EE_DB_USER is empty

	if [[ $EE_DB_USER = "" ]]; then
 		EE_DB_USER=$ee_replace_dot
 	fi

 	if [[ $EE_DB_PASS = "" ]]; then
 		EE_DB_PASS=$ee_random
 	fi

 	# Fix MySQL username ERROR 1470 (HY000)
 	if [[ $(echo -n $EE_DB_USER | wc -c) -gt 16 ]]; then
 		ee_lib_echo "Autofix MySQL username (ERROR 1470 (HY000)), please wait..."
 		local ee_random10=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 10 | head -n1)
		EE_DB_USER=$(echo $EE_DB_USER | cut -c1-16 | sed "s/.\{10\}$/$ee_random10/")
 	fi

 	# Create MySQL database
	echo -e "EE_DB_NAME = $EE_DB_NAME \nEE_DB_USER = $EE_DB_USER \nEE_DB_PASS = $EE_DB_PASS" &>> $EE_COMMAND_LOG
	mysql -e "create database \`$EE_DB_NAME\`" \
	|| ee_lib_error "Unable to create $EE_DB_NAME database, exit status = " $?

	# Create MySQL User
	mysql -e "create user '$EE_DB_USER'@'$EE_MYSQL_HOST' identified by '$EE_DB_PASS'" \
	|| ee_lib_error "Unable to create $EE_DB_USER database user, exit status = " $?

	# Grant permission
	mysql -e "grant all privileges on \`$EE_DB_NAME\`.* to '$EE_DB_USER'@'$EE_MYSQL_HOST'" \
	|| ee_lib_error "Unable to grant privileges for $EE_DB_USER database user, exit status = " $?
	mysql -e "flush privileges"	
}
