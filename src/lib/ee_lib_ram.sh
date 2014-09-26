# EasyEngine RAM based settings

function ee_lib_ram()
{
	# Detect RAM and SWAP of System
	readonly EE_TOTAL_RAM=$(free -m | grep -i Mem | awk '{ print $2 }')
	readonly EE_TOTAL_SWAP=$(free -m | grep -i Swap | awk '{ print $2 }')

	# RAM < 512MB
	if [ $EE_TOTAL_RAM -le 512 ]; then
		EE_OPCACHE_SIZE="64"
		EE_MEMCACHE_SIZE="64"
		EE_PHP_MAX_CHILDREN="10"
		EE_SETUP_MAILSCANNER="no"
		EE_SWAP="1024"
	# RAM > 512MB and RAM < 1024MB
	elif [ $EE_TOTAL_RAM -gt 512 ] && [ $EE_TOTAL_RAM -le 1024 ]; then
		EE_OPCACHE_SIZE="128"
		EE_MEMCACHE_SIZE="128"
		EE_PHP_MAX_CHILDREN="10"
		EE_SWAP="1024"
	# RAM > 1024MB and RAM < 2048MB
	elif [ $EE_TOTAL_RAM -gt 1024 ] && [ $EE_TOTAL_RAM -le 2048 ]; then
		EE_OPCACHE_SIZE="256"
		EE_MEMCACHE_SIZE="256"
		EE_PHP_MAX_CHILDREN="20"
	# RAM > 2048MB and RAM < 4096MB
	elif [ $EE_TOTAL_RAM -gt 2048 ] && [ $EE_TOTAL_RAM -le 4096 ]; then
		EE_OPCACHE_SIZE="512"
		EE_MEMCACHE_SIZE="512"
		EE_PHP_MAX_CHILDREN="40"	
	# RAM > 4096MB and RAM < 8192MB
	elif [ $EE_TOTAL_RAM -gt 4096 ] && [ $EE_TOTAL_RAM -le 8192 ]; then
		EE_OPCACHE_SIZE="512"
		EE_MEMCACHE_SIZE="1024"
		EE_PHP_MAX_CHILDREN="80"
	# RAM > 8192MB and RAM < 16384MB
	elif [ $EE_TOTAL_RAM -gt 8192 ] && [ $EE_TOTAL_RAM -le 16384 ]; then
		EE_OPCACHE_SIZE="512"
		EE_MEMCACHE_SIZE="2048"
		EE_PHP_MAX_CHILDREN="100"
	# RAM > 16384MB
	elif [ $EE_TOTAL_RAM -gt 16384 ]; then
		EE_OPCACHE_SIZE="512"
		EE_MEMCACHE_SIZE="2048"
		EE_PHP_MAX_CHILDREN="100"
	fi
}
