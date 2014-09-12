# Clean all cache

function ee_mod_cache_clean()
{

	for ee_param in $@ ; do

		if [ "$ee_param" = "" ] || [ "$ee_param" = "fastcgi" ] || [ "$ee_param" = "all" ]; then
			# Clean fastcgi cache 
			ee_lib_echo "Cleaning FastCGI cache, please wait .... "
			if [ -d /var/run/nginx-cache/ ]; then
				rm -rf /var/run/nginx-cache/* &>> $EE_COMMAND_LOG 
			fi
		fi
		
		if [ "$ee_param" = "memcache" ] || [ "$ee_param" = "all" ]; then
			# Clean memcache
			ee_lib_echo "Cleaning Memcache, please wait .... "
			dpkg --get-selections | grep -v deinstall | grep memcached &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Memcache not installed, exit status = " $?
			if [ $? -eq 0 ];then
				service memcached restart &>> $EE_COMMAND_LOG 
			fi
		fi

		if [ "$ee_param" = "opcache" ] || [ "$ee_param" = "all" ]; then
			# Clean opcache
			ee_lib_echo "Cleaning OPcache, please wait .... "
			wget --no-check-certificate --spider -q https://127.0.0.1:22222/cache/opcache/opgui.php?page=reset \
			|| ee_lib_error "Unable to clean OPcache, exit status = " $?

		fi
	done

}
