# Import MySQL slow log to Anememoter

function ee_lib_import_slow_log()
{

	dpkg --compare-versions $(pt-query-digest --version | awk '{print $2 }') ge 2.2
	if [ $? -eq 0 ]; then
		ee_anemometer_history=history
	else
		ee_anemometer_history=review-history
	fi

	pt-query-digest --user=anemometer --password=anemometer_password \
	--review D=slow_query_log,t=global_query_review \
	--${ee_anemometer_history} D=slow_query_log,t=global_query_review_history \
	--no-report --limit=0% --filter=" \$event->{Bytes} = length(\$event->{arg}) and \$event->{hostname}=\"$EE_MYSQL_GRANT_HOST\"" /var/log/mysql/mysql-slow.log
}
