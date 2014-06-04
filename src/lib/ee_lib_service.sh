# Services Start/Stop/Restart/Reload
# ee_lib_service nginx start
# ee_lib_service nginx stop
# ee_lib_service nginx restart
# ee_lib_service nginx php5-fpm mysql postfix restart

function ee_lib_service()
{
	for ee_service_name in $@; do
		if [ $ee_service_name != ${@: -1} ]; then

			# Check nginx and php5-fpm test before start/stop/restart/reload 
			if [ $ee_service_name = "nginx" ]; then

				# Adjust nginx server_names_hash_bucket_size
				$ee_service_name -t 2>&1 | grep server_names_hash_bucket_size &>> EE_COMMAND_LOG
				if [ $? -eq 0 ];then
					EE_NGINX_CALCULATION=$(echo "l($(ls /etc/nginx/sites-enabled/ | wc -c))/l(2)+2" | bc -l)
					EE_NGINX_SET_BUCKET=$(echo "2^$EE_NGINX_CALCULATION" | bc -l 2> /dev/null)
					sed -i "s/.*server_names_hash_bucket_size.*/\tserver_names_hash_bucket_size $EE_NGINX_SET_BUCKET;/" /etc/nginx/nginx.conf
				fi

				# Test and start/stop/restart/reload nginx service
				$ee_service_name -t &>> EE_COMMAND_LOG \
				&& service $ee_service_name ${@: -1} \
				|| ee_lib_error "Unable to execute service $ee_service_name ${@: -1}, exit status = " $?

			elif [ $ee_service_name = "php5-fpm" ]; then

				# Test and start/stop/restart/reload php5-fpm service
				$ee_service_name -t &>> EE_COMMAND_LOG \
				&& service $ee_service_name ${@: -1} \
				|| ee_lib_error "Unable to execute service $ee_service_name ${@: -1}, exit status = " $?

			else

				# start/stop/restart/reload services
				service $ee_service_name ${@: -1} \
				|| ee_lib_error "Unable to execute service $ee_service_name ${@: -1}, exit status = " $?

			fi

		fi
	done
}
