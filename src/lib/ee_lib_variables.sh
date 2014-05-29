# Define global variables

readonly EE_LOG_DIR=/var/log/easyengine
readonly EE_COMMAND_LOG=/var/log/easyengine/ee.log
readonly EE_ERROR_LOG=/var/log/easyengine/error.log
readonly EE_LINUX_DISTRO=$(lsb_release -i |awk '{print $3}')
readonly EE_CONFIG_GET=$(echo "git config --file /etc/easyengine/ee.conf")
readonly EE_CONFIG_SET=$(echo "git config --file /etc/easyengine/ee.conf" --replace-all)
readonly EE_IP_ADDRESS=$($EE_CONFIG_GET system.ip-address | cut -d'=' -f2 | sed 's/ //g' | tr ',' '\n')
readonly EE_APT_GET=$($EE_CONFIG_GET system.apt-get-assume-yes | grep -i true &> /dev/null && echo apt-get -y || echo apt-get)

# Distribution specific variable
if [ "$EE_LINUX_DISTRO" == "Ubuntu" ];	then
	#Specify nginx package
	readonly EE_NGINX_PACKAGE=nginx-custom

elif [ "$EE_LINUX_DISTRO" == "Debian" ]; then
	# Specify nginx package
	readonly EE_NGINX_PACKAGE=nginx-full
	# Detect Debian version
	readonly EE_DEBIAN_VERSION=$(lsb_release -r | awk '{print($2)}' | cut -d'.' -f1)

fi
