#Clean all cache

function ee_cache_clean()
{
	#Clean fastcgi cache 
	rm -rf $(grep fastcgi_cache_path /etc/nginx/conf.d/fastcgi.conf | awk '{ print $2 }' | sed 's/$/\/*/g') \
	|| ee_lib_error "Unable to clean fastcgi cache, exit status = " $? 

	#Clean memcache
	service memcached restart &>> $EE_COMMAND_LOG || ee_lib_error "Unable to clean memcache, exit status = " $? 
}