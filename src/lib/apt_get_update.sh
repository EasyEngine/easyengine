# Update apt-get cache

function apt_get_update()
{
	echo_blue "apt-get update, Please Wait..."
	apt-get update &>> $EE_LOG || ee_error "Unable to execute apt-get update, exit status = " $?
}
