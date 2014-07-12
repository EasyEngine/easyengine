# Setup EasyEngine admin port

function ee_mod_secure_port()
{
	read -p "EasyEngine admin port [22222]: " ee_port

	# If enter is pressed, set 22222
	if [[ $ee_port = "" ]]; then
		ee_port=22222
	fi
	
	if [ "$EE_LINUX_DISTRO" == "Ubuntu" ];	then
		sed -i "s/listen.*/listen $ee_port default_server ssl spdy;/" /etc/nginx/sites-available/22222 \
		|| ee_lib_error "Unable to change EasyEngine admin port, exit status = " $?
	elif [ "$EE_LINUX_DISTRO" == "Debian" ]; then
		# Dotdeb nginx repository doesn't support spdy
		sed -i "s/listen.*/listen $ee_port default_server ssl;/" /etc/nginx/sites-available/22222 \
		|| ee_lib_error "Unable to change EasyEngine admin port, exit status = " $?
	fi
}
