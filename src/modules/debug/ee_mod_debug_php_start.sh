# Enables PHP debug mode

function ee_mod_debug_php_start()
{
	#  Lets disable PHP restart trigger
	EE_DEBUG_PHP=""

	if [ -z $EE_DEBUG_SITENAME ]; then
		grep -B2 9001 /etc/nginx/conf.d/upstream.conf \
		| grep php &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			ee_lib_echo "Enable PHP5-FPM slow log, please wait..."
			sed -i "4 s/9000/9001/" /etc/nginx/conf.d/upstream.conf

			# Lets trigger the NGINX reload
			EE_DEBUG_NGINX="--nginx"
		else
			ee_lib_echo "PHP5-FPM slow log already enabled"
		fi
	else
		grep 9001 /etc/nginx/sites-available/$EE_DOMAIN &>> $EE_COMMAND_LOG
		if [ $? -ne 0 ]; then
			ee_lib_echo "Enable PHP5-FPM slow log for $EE_DOMAIN, please wait..."
			sed -i "s/fastcgi_pass.*/fastcgi_pass debug;/g" /etc/nginx/sites-available/$EE_DOMAIN

			# Lets trigger the NGINX reload
			EE_DEBUG_NGINX="--nginx"
		else
			ee_lib_echo "PHP5-FPM slow log already enabled for $EE_DOMAIN"
		fi
	fi
}
