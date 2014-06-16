# Removes https://, http://, www from site name

function ee_lib_prcoess_site_name()
{
	# Check EE_SITE_NAME is empty or not
	if [ -z "$EE_SITE_NAME" ]
	then
		# Ask users to enter domain name
		read -p "Enter domain name: " EE_SITE_NAME
		# Remove http://  https:// & www.
		EE_DOMAIN=$(echo $EE_SITE_NAME | tr 'A-Z' 'a-z' |  sed "s'http://''" | sed "s'https://''" | sed "s'www.''" | sed "s'/''")
	else
		# Remove http://  https:// & www.
		EE_DOMAIN=$(echo $EE_SITE_NAME | tr 'A-Z' 'a-z' |  sed "s'http://''" | sed "s'https://''" | sed "s'www.''" | sed "s'/''")
	fi

	# Remove http://  https:// for WordPress setup (www.example.com)
	EE_WWWDOMAIN=$(echo $EE_SITE_NAME | tr 'A-Z' 'a-z' |  sed "s'http://''" | sed "s'https://''" | sed "s'/''")
}
