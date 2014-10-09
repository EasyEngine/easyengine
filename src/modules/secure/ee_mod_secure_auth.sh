# Setup HTTP authentication

function ee_mod_secure_auth()
{
	# Random characters
	local ee_random=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)
	
	read -p "Provide HTTP authentication user name [$(git config user.name)]: " EE_HTTP_AUTH_USER
	read -sp "Provide HTTP authentication password [$ee_random]: " EE_HTTP_AUTH_PASS
	echo

	# If enter is pressed, set git config user.name
	if [[ $EE_HTTP_AUTH_USER = "" ]]; then
		EE_HTTP_AUTH_USER=$(git config user.name)
	fi

	if [[ $EE_HTTP_AUTH_PASS = "" ]]; then
		EE_HTTP_AUTH_PASS=$ee_random
	fi

	# Add HTTP authentication details
	echo
	ee_lib_echo "HTTP authentication username: $EE_HTTP_AUTH_USER" &>> $EE_COMMAND_LOG
	ee_lib_echo "HTTP authentication password: $EE_HTTP_AUTH_PASS" &>> $EE_COMMAND_LOG

	# Generate htpasswd-ee file
	printf "$EE_HTTP_AUTH_USER:$(openssl passwd -crypt $EE_HTTP_AUTH_PASS 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
}
