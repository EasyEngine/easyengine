# Update apt-get cache
function APT_GET_UPDATE()
{
	ECHO_BLUE "apt-get update, Please Wait..."
	apt-get update &>> $EE_LOG || EE_ERROR "Unable to execute apt-get update"
}
