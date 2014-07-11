# Setup HTTP authentication

function ee_mod_secure_auth()
{
	local ee_http_auth_user ee_http_auth_pass
	
	read -sp "Provide HTTP authentication user name [easyengine]: " ee_http_auth_user
	read -sp "Provide HTTP authentication password [easyengine]: " ee_http_auth_pass

	# If enter is pressed, set easyengine
	if [[ $ee_http_auth_user = "" ]]; then
		ee_http_auth_user=easyengine
	fi

	if [[ $ee_http_auth_pass = "" ]]; then
		ee_http_auth_pass=easyengine
	fi

	# Add HTTP authentication details
	ee_lib_echo "HTTP authentication username: $ee_http_auth_user" &>> $EE_COMMAND_LOG
	ee_lib_echo "HTTP authentication password: $ee_http_auth_pass" &>> $EE_COMMAND_LOG

	# Generate htpasswd-ee file
	printf "$ee_http_auth_user:$(openssl passwd -crypt $ee_http_auth_pass 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
}
