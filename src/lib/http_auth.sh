# Setup HTTP authentication

function http_auth()
{
	local http_auth_user http_auth_pass
	
	read -sp "Provide HTTP authentication user name [easyengine]: " http_auth_user
	read -sp "Provide HTTP authentication password [easyengine]: " http_auth_pass

	# If enter is pressed, set easyengine
	if [[ $http_auth_user = "" ]]; then
		http_auth_user=easyengine
	fi

	if [[ $http_auth_pass = "" ]]; then
		http_auth_pass=easyengine
	fi

	# Add HTTP authentication details
	sed -i "s/http_auth_user.*/http_auth_user = $http_auth_user/" /etc/easyengine/ee.conf
	sed -i "s/http_auth_pass.*/http_auth_pass = $http_auth_pass/" /etc/easyengine/ee.conf

	# Generate htpasswd-ee file
	printf "$http_auth_user:$(openssl passwd -crypt $http_auth_pass 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
}