# White list IP address

function ee_mod_secure_ip()
{
	read -p "Enter the comma separated IP addresses to white list [127.0.0.1]: " ee_ip

	# If enter is pressed, set 127.0.0.1
	if [[ $ee_ip = "" ]]; then
		ee_ip=127.0.0.1
	fi

	# Check weather IP address already present or not
	for ee_check_ip in $(echo $ee_ip | cut -d'=' -f2 | sed 's/ //g' | tr ',' '\n'); do
			grep $ee_check_ip /etc/easyengine/ee.conf &>> /dev/null
			if [ $? -ne 0 ]; then
				ee_update_ip="$ee_update_ip $ee_check_ip"
			fi
	done

	# Update ee.conf
	$EE_CONFIG_SET stack.ip-address "$($EE_CONFIG_GET stack.ip-address),$(echo $ee_update_ip | tr ' ' ',')"
	
	# White list IP address
	EE_IP_ADDRESS=$($EE_CONFIG_GET stack.ip-address | cut -d'=' -f2 | sed 's/ //g' | tr ',' '\n')
	if [ -n "$EE_IP_ADDRESS" ]; then
		sed -i "/allow.*/d" /etc/nginx/common/acl.conf
		for ee_whitelist_ip_address in $(echo $EE_IP_ADDRESS);do
      sed -i "/deny/i $(echo allow $ee_whitelist_ip_address\;)" /etc/nginx/common/acl.conf
		done
	fi
}
