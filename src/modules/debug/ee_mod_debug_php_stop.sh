# Disables PHP debug mode

function ee_mod_debug_php_stop()
{
	#  Lets disable PHP restart trigger
	EE_DEBUG_PHP=""

	if [ -z $EE_DEBUG_SITENAME ]; then
		grep -B2 9001 /etc/nginx/conf.d/upstream.conf \
		| grep php &>> $EE_COMMAND_LOG
		if [ $? -eq 0 ]; then
			ee_lib_echo "Disable PHP5-FPM slow log, please wait..."
			sed -i "4 s/9001/9000/" /etc/nginx/conf.d/upstream.conf
			
			# Lets trigger the NGINX reload
			EE_DEBUG_NGINX="--nginx"
		else
			ee_lib_echo "PHP5-FPM slow log already disabled"
		fi
	else
		grep "fastcgi_pass debug;" /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
		if [ $? -eq 0 ]; then
			ee_lib_echo "Disable PHP5-FPM slow log for $EE_DOMAIN, please wait..."
			sed -i "s/fastcgi_pass.*/fastcgi_pass php;/g" /etc/nginx/sites-available/$EE_DOMAIN

			# Lets trigger NGINX reload
			EE_DEBUG_NGINX="--nginx"
		else
			ee_lib_echo "PHP5-FPM slow log already disabled for $EE_DOMAIN"
		fi
	fi
}
