# Display information about website

function ee_mod_site_info()
{
	local ee_site_status=$(ls /etc/nginx/sites-enabled/$EE_DOMAIN &> /dev/null && echo Enable || echo Disable)
	local ee_site_info=$(head -n1 /etc/nginx/sites-available/$EE_DOMAIN | grep "NGINX CONFIGURATION" | rev | cut -d' ' -f3,4,5,6,7 | rev | cut -d ' ' -f2,3,4,5)
	local ee_access_log=$(grep access_log /etc/nginx/sites-available/$EE_DOMAIN | grep "/var/log/nginx/" | awk '{print($2)}' | cut -d ';' -f1)
	local ee_error_log=$(grep error_log /etc/nginx/sites-available/$EE_DOMAIN | grep "/var/log/nginx/" | awk '{print($2)}' | cut -d ';' -f1)
	local ee_webroot=$(grep root /etc/nginx/sites-available/$EE_DOMAIN | grep htdocs | awk '{print($2)}'  | cut -d ';' -f1)
	local ee_db_name=$(grep DB_NAME /var/www/$EE_DOMAIN/*-config.php 2> /dev/null | cut -d"'" -f4)
	local ee_db_user=$(grep DB_USER /var/www/$EE_DOMAIN/*-config.php 2> /dev/null | cut -d"'" -f4)
	local ee_db_pass=$(grep DB_PASS /var/www/$EE_DOMAIN/*-config.php 2> /dev/null | cut -d"'" -f4)

	ee_lib_echo "Information about $EE_DOMAIN:"
	ee_lib_echo_escape "\nNginx configuration\t \033[37m$ee_site_info ($ee_site_status)"
	ee_lib_echo_escape "access_log\t\t \033[37m$ee_access_log"
	ee_lib_echo_escape "error_log\t\t \033[37m$ee_error_log"
	ee_lib_echo_escape "Webroot\t\t\t \033[37m$ee_webroot"
	ee_lib_echo_escape "DB_NAME\t\t\t \033[37m$ee_db_name"
	ee_lib_echo_escape "DB_USER\t\t\t \033[37m$ee_db_user"
	ee_lib_echo_escape "DB_PASS\t\t\t \033[37m$ee_db_pass"
}
