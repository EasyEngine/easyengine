# Clean NGINX FastCGI, Memcache, OPcache cache

function ee_mod_clean()
{

	if [ $# -eq 0 ]; then
		ee_clean_fastcgi="fastcgi"
	fi
	for ee_clean in $@; do
		if [ "$ee_clean" = "" ] || [ "$ee_clean" = "fastcgi" ]; then
			ee_clean_fastcgi="fastcgi"
		elif [ "$ee_clean" = "memcache" ]; then
			ee_clean_memcache="memcache"
		elif [ "$ee_clean" = "opcache" ]; then
			ee_clean_opcache="opcache"
		elif [ "$ee_clean" = "all" ]; then
			ee_clean_fastcgi="fastcgi"
			ee_clean_memcache="memcache"
			ee_clean_opcache="opcache"
		else
			ee_lib_error "$ee_clean invalid option, exit status = " $?
		fi
	done

	# Clean NGINX FastCGI cache 
	if [ "$ee_clean_fastcgi" = "fastcgi" ]; then
		if [ -d /var/run/nginx-cache/ ]; then
			ee_lib_echo "Cleaning NGINX FastCGI cache, please wait..."
			rm -rf /var/run/nginx-cache/* &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to clean FastCGI cache, exit status = " $?
		fi
	fi

	# Clean Memcache
	if [ "$ee_clean_memcache" = "memcache" ]; then
		dpkg --get-selections | grep -v deinstall | grep memcached &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Memcache not installed, exit status = " $?

		if [ $? -eq 0 ]; then
			ee_lib_echo "Cleaning Memcached, please wait..."
			service memcached restart &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to restart memcached, exit status = " $?
		fi
	fi

	# Clean OPcache
	if [ "$ee_clean_opcache" = "opcache" ]; then
		ee_lib_echo "Cleaning OPcache, please wait..."
		wget --no-check-certificate --spider -q https://127.0.0.1:22222/cache/opcache/opgui.php?page=reset \
		|| ee_lib_error "Unable to clean OPcache, exit status = " $?
	fi
}
