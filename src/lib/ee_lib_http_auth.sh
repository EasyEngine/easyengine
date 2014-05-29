# Setup HTTP authentication

function ee_lib_http_auth()
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
	$EE_CONFIG_SET auth.user $ee_http_auth_user
	$EE_CONFIG_SET auth.password $ee_http_auth_pass

	# Generate htpasswd-ee file
	printf "$ee_http_auth_user:$(openssl passwd -crypt $ee_http_auth_pass 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
}