# Fetch and install dotdeb GnuPG key

function dot_deb_gpg_key()
{
	wget --no-check-certificate -cqO /tmp/dotdeb.gpg http://www.dotdeb.org/dotdeb.gpg || ee_error "Unable to download dotdeb GnuPG key, exit status = " $?
	apt-key add /tmp/dotdeb.gpg &>> $EE_LOG || ee_error "Unable to add dotdeb GnuPG key, exit status = " $?
}
