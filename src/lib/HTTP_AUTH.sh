# Setup http authentication
function HTTP_AUTH()
{
	read -sp "Provide http authentication username [easyengine]: " HTTP_AUTH_USER
	read -sp "Provide http authentication password [easyengine]: " HTTP_AUTH_PASS

	# If enter is pressed, set easyengine
	if [[ $HTTP_AUTH_USER = "" ]]; then
		HTTP_AUTH_USER=easyengine
	fi

	if [[ $HTTP_AUTH_PASS = "" ]]; then
		HTTP_AUTH_PASS=easyengine
	fi

	# Add http authentication details
	sed -i "s/HTTP_AUTH_USER.*/HTTP_AUTH_USER = $HTTP_AUTH_USER/" /etc/easyengine/ee.conf
	sed -i "s/HTTP_AUTH_PASS.*/HTTP_AUTH_PASS = $HTTP_AUTH_PASS/" /etc/easyengine/ee.conf

	# Generate htpasswd-ee file
	printf "$HTTP_AUTH_USER:$(openssl passwd -crypt $HTTP_AUTH_PASS 2> /dev/null)\n" > /etc/nginx/htpasswd-ee 2> /dev/null
}