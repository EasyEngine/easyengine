# Remove EasyEngine (ee) admin utilities

function ee_ven_remove_utils()
{
	# Remove EasyEngine (ee) admin utilities
	ee_lib_echo "Remove EasyEngine (ee) admin utilities, please wait..."
	rm -rf /var/www/22222/htdocs/cache/ /var/www/22222/htdocs/fpm /var/www/22222/htdocs/php /var/www/22222/htdocs/db/anemometer \
	|| ee_lib_error "Unable to remove EasyEngine (ee) admin utilities"

	# Drop Anemometer database
	mysql -e "drop database slow_query_log" \
	|| ee_lib_error "Unable to drop slow_query_log database, exit status = " $?
}
