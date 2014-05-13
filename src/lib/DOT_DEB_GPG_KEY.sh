# Fetch and install dotdeb GnuPG key
function DOT_DEB_GPG_KEY()
{
	wget --no-check-certificate -cqO /tmp/dotdeb.gpg http://www.dotdeb.org/dotdeb.gpg || EE_ERROR "Unable to download dotdeb GnuPG key"
	apt-key add /tmp/dotdeb.gpg &>> $EE_LOG || EE_ERROR "Unable to add dotdeb GnuPG key"
}
