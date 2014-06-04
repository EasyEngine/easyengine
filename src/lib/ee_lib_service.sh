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
			if [ $ee_service_name = "nginx" ] || [ $ee_service_name = "php5-fpm" ]; then
				$ee_service_name -t &> EE_COMMAND_LOG \
				&& service $ee_service_name ${@: -1} \
				|| ee_lib_error "Unable to execute service $ee_service_name ${@: -1}, exit status = " $?
			else
				service $ee_service_name ${@: -1} \
				|| ee_lib_error "Unable to execute service $ee_service_name ${@: -1}, exit status = " $?
			fi

		fi
	done
}
