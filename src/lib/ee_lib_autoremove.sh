# Execute: apt-get autoremove

function ee_lib_autoremove()
{
	ee_lib_echo "Removing unwanted packages, please wait..."
	$EE_APT_GET autoremove
}
