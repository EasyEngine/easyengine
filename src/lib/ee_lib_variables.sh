# Define global variables

# EasyEngine version
readonly EE_VERSION='2.2.1'

# WP-CLI version
readonly EE_WP_CLI_VERSION='0.17.0'

# Adminer version
readonly EE_ADMINER_VERSION='4.1.0'

# Roundcube Version
readonly EE_ROUNDCUBE_VERSION='1.0.3'

# ViMbAdmin Version
readonly EE_VIMBADMIN_VERSION='3.0.10'

# EasyEngine Date variable for backup
readonly EE_DATE=$(date +%d%b%Y%H%M%S)

# Log only single time
# ee site create example.com called ee stack install nginx
# So in log file all logs written twice
if [ -n "$EE_LOG" ]; then
        EE_COMMAND_LOG=/dev/null
else
        EE_COMMAND_LOG=/var/log/easyengine/ee.log
fi

readonly EE_LOG_DIR=/var/log/easyengine
readonly EE_ERROR_LOG=/var/log/easyengine/error.log
readonly EE_LINUX_DISTRO=$(lsb_release -i |awk '{print $3}')
readonly EE_CONFIG_GET=$(echo "git config --file /etc/easyengine/ee.conf")
readonly EE_CONFIG_SET=$(echo "git config --file /etc/easyengine/ee.conf" --replace-all)
readonly EE_APT_GET=$($EE_CONFIG_GET stack.apt-get-assume-yes | grep -i true &> /dev/null && echo apt-get -y || echo apt-get)
EE_IP_ADDRESS=$($EE_CONFIG_GET stack.ip-address | cut -d'=' -f2 | sed 's/ //g' | tr ',' '\n')

# Distribution specific variable
if [ "$EE_LINUX_DISTRO" == "Ubuntu" ];	then
	#Specify nginx package
	readonly EE_NGINX_PACKAGE=nginx-custom
elif [ "$EE_LINUX_DISTRO" == "Debian" ]; then
	# Specify nginx package
	readonly EE_NGINX_PACKAGE=nginx-full
	# Detect Debian version
	readonly EE_DEBIAN_VERSION=$(lsb_release -sc)
fi

# Find php user-name
if [ -f /etc/php5/fpm/pool.d/www.conf ]; then
	readonly EE_PHP_USER=$(grep ^user /etc/php5/fpm/pool.d/www.conf | cut -d'=' -f2 | cut -d' ' -f2)
else
	# At installation time: ee stack install
	# File /etc/php5/fpm/pool.d/www.conf not present
	readonly EE_PHP_USER=www-data
fi

# Find out MySQL hostname
if [ -z $(git config --file $HOME/.my.cnf client.host) ]; then
	readonly EE_MYSQL_HOST=localhost
else
	readonly EE_MYSQL_HOST=$(git config --file $HOME/.my.cnf client.host)
fi

# Find out MySQL client-host to setup grants
if [ -z $($EE_CONFIG_GET mysql.grant-host) ]; then
	readonly EE_MYSQL_GRANT_HOST=localhost
else
	readonly EE_MYSQL_GRANT_HOST=$($EE_CONFIG_GET mysql.grant-host)
fi
