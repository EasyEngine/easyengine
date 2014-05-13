# Define variables
LOG_DIR=/var/log/easyengine
EE_LOG=/var/log/easyengine/ee.log
ERROR_LOG=/var/log/easyengine/error.log
LINUX_DISTRO=$(lsb_release -i |awk '{print $3}')
IP_ADDRESS=$(grep ip_address /etc/easyengine/ee.conf | cut -d'=' -f2 | sed 's/ //g' | tr ',' '\n')
APT_GET=$(grep apt-get-assume-yes /etc/easyengine/ee.conf | grep -i true &> /dev/null && echo apt-get -y)
