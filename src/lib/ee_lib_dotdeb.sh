# Fetch and install Dotdeb.org GnuPG key

function ee_lib_dotdeb()
{
	wget --no-check-certificate -cO /tmp/dotdeb.gpg http://www.dotdeb.org/dotdeb.gpg \
	&>> $EE_COMMAND_LOG || ee_lib_error "Unable to download Dotdeb.org GnuPG key, exit status = " $?
	apt-key add /tmp/dotdeb.gpg &>> $EE_COMMAND_LOG \
	|| ee_lib_error "Unable to add Dotdeb.org GnuPG key, exit status = " $?
}
