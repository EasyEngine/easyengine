

function ee_mod_debug_kill()
{
	if [ "$EE_DEBUG" = "--start" ]; then
		if [ -z "$EE_DEBUG_SITENAME" ]; then
			ee debug --stop
		else
			ee debug --stop $EE_DEBUG_SITENAME
		fi
	fi

	# Unset trap so we don't get infinite loop
	trap - EXIT

	# Flush file system buffers
	# More details: info coreutils 'sync invocation'
	sync

	# Successfull exit
	exit 0;
}

trap "ee_mod_debug_kill" EXIT
