# Clean all cache

function ee_cache_clean()
{
	# Clean fastcgi cache 
	if [ -d /var/run/nginx-cache/ ]; then
		rm -rf /var/run/nginx-cache/* &>> $EE_COMMAND_LOG 
	fi

	# Clean memcache
	dpkg --get-selections | grep -v deinstall | grep memcached &>> $EE_COMMAND_LOG

	if [ $? -eq 0 ];then
		service memcached restart &>> $EE_COMMAND_LOG 
	fi
}
