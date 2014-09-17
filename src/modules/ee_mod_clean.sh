# Clean NGINX FastCGI, Memcache, OPcache cache

function ee_mod_clean()
{
	if [ "$@" = "" ] || [ "$@" = "fastcgi" ]; then
		EE_CLEAN_FASTCGI="fastcgi"
	elif [ "$@" = "memcache" ]; then
		EE_CLEAN_MEMCACHE="memcache"
	elif [ "$@" = "opcache" ]; then
		EE_CLEAN_OPCACHE="opcache"
	elif [ "$@" = "all" ]; then
		EE_CLEAN_FASTCGI="fastcgi"
		EE_CLEAN_MEMCACHE="memcache"
		EE_CLEAN_OPCACHE="opcache"
	fi

	if [ "$EE_CLEAN_FASTCGI" = "fastcgi" ]; then
		ee_lib_echo "Cleaning NGINX FastCGI cache, please wait..."
		if [ -d /var/run/nginx-cache/ ]; then
			rm -rf /var/run/nginx-cache/* &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to clean FastCGI cache, exit status = " $?
		fi
	fi

	if [ "$EE_CLEAN_MEMCACHE" = "memcache" ]; then
		dpkg --get-selections | grep -v deinstall | grep memcached &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Memcache not installed, exit status = " $?

		if [ $? -eq 0 ]; then
			ee_lib_echo "Cleaning Memcached, please wait..."
			service memcached restart &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to restart memcached, exit status = " $?
		fi
	fi

	if [ "$EE_CLEAN_OPCACHE" = "memcache" ]; then
		ee_lib_echo "Cleaning Memcached, please wait..."
		wget --no-check-certificate --spider -q https://127.0.0.1:22222/cache/opcache/opgui.php?page=reset \
		|| ee_lib_error "Unable to clean OPcache, exit status = " $?
	fi
}