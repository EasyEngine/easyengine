# Execute: apt-get update

function ee_lib_apt_get_update()
{
	ee_lib_echo "Executing apt-get update, please wait..."
	apt-get update &>> $EE_COMMAND_LOG || ee_lib_error "Unable to execute apt-get update, exit status = " $?
}
