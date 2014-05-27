# Define global variables

readonly LOG_DIR=/var/log/easyengine
readonly EE_LOG=/var/log/easyengine/ee.log
readonly ERROR_LOG=/var/log/easyengine/error.log
readonly LINUX_DISTRO=$(lsb_release -i |awk '{print $3}')
readonly IP_ADDRESS=$(grep ip_address /etc/easyengine/ee.conf | cut -d'=' -f2 | sed 's/ //g' | tr ',' '\n')
readonly APT_GET=$(grep apt-get-assume-yes /etc/easyengine/ee.conf | grep -i true &> /dev/null && echo apt-get -y)

if [ "$LINUX_DISTRO" == "Debian" ]; then
	# Detect Debian version
	readonly DEBIAN_VERSION=$(lsb_release -r | awk '{print($2)}' | cut -d'.' -f1)
fi
