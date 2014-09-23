# Setup HTTP authentication

function ee_mod_secure_auth()
{
	local ee_http_auth_user ee_http_auth_pass

	# Random characters
	local ee_random=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 15 | head -n1)
	
	read -p "Provide HTTP authentication user name [$(git config user.name)]: " ee_http_auth_user
	read -sp "Provide HTTP authentication password [$ee_random]: " ee_http_auth_pass
	echo

	# If enter is pressed, set git config user.name
	if [[ $ee_http_auth_user = "" ]]; then
		ee_http_auth_user=$(git config user.email)
	fi

	if [[ $ee_http_auth_pass = "" ]]; then
		ee_http_auth_pass=$ee_random
	fi

	# Add HTTP authentication details
	ee_lib_echo "HTTP authentication username: $ee_http_auth_user" 
	ee_lib_echo "HTTP authentication password: $ee_http_auth_pass"

	# Generate htpasswd-ee file
	printf "$ee_http_auth_user:$(openssl passwd -crypt $ee_http_auth_pass 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
}
