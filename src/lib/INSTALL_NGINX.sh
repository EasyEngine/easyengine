# Install nginx
function INSTALL_NGINX()
{
	ECHO_BLUE "Installing $NGINXPACKAGE, please wait..."
	$APT_GET install $NGINXPACKAGE || EE_ERROR "Unable to install $NGINXPACKAGE"
}