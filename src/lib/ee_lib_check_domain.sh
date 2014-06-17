# Check domain name

function ee_lib_check_domain()
{
	# Check if domain name empty
	while [ -z $1 ]; do
		# Ask user to enter domain name
		read -p "Enter domain name: " EE_DOMAIN
	done

	# Remove http://  https:// & www.
	EE_DOMAIN=$(echo $EE_DOMAIN | tr 'A-Z' 'a-z' |  sed "s'http://''" | sed "s'https://''" | sed "s'www.''" | sed "s'/''")
	
	# Remove http:// https:// For WordPress Setup (www.example.com)
	EE_WWW_DOMAIN=$(echo $EE_DOMAIN | tr 'A-Z' 'a-z' | sed "s'http://''" | sed "s'https://''" | sed "s'/''")
}

