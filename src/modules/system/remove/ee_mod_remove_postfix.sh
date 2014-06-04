# Remove Postfix package

function ee_mod_remove_postfix()
{
	ee_lib_echo "Removing Postfix, please wait..."
	$EE_APT_GET remove postfix || ee_lib_error "Unable to remove Postfix, exit status = " $?
}
