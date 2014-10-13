# Import MySQL slow log to Anememoter

function ee_lib_import_slow_log()
{

	if [ -d /var/www/22222/htdocs/db/anemometer ]; then
		if [ -f /var/log/mysql/mysql-slow.log ]; then
			ee_lib_echo "Importing MySQL slow log, please wait..."
			dpkg --compare-versions $(pt-query-digest --version | awk '{print $2 }') ge 2.2
			if [ $? -eq 0 ]; then
				ee_anemometer_history=history
			else
				ee_anemometer_history=review-history
			fi

			pt-query-digest --user=anemometer --password=anemometer_password \
			--review D=slow_query_log,t=global_query_review \
			--${ee_anemometer_history} D=slow_query_log,t=global_query_review_history \
			--no-report --limit=0% --filter=" \$event->{Bytes} = length(\$event->{arg}) and \$event->{hostname}=\"anemometer-mysql\"" /var/log/mysql/mysql-slow.log
		else
			ee_lib_echo_fail "Failed to find MySQL slow log file, enable MySQL slow log"
		fi
	else
		ee_lib_echo_fail "Anemometer is not installed"
	fi
}
