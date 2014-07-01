# Execute: ee debug --stop
# When ee debug module run with -i flag
# This function is called when user press CTRL+C

function ee_mod_debug_stop()
{
	if [ "$EE_DEBUG" = "--start" ]; then
		if [ -z "$EE_DOMAIN" ]; then
			ee debug --stop
		else
			ee debug --stop $EE_DOMAIN
		fi
	fi

	# Unset trap so we don't get infinite loop
	trap - EXIT

	# Flush file system buffers
	# More details: info coreutils 'sync invocation'
	sync

	# Successful exit
	exit 0;
}

trap "ee_mod_debug_stop" EXIT
