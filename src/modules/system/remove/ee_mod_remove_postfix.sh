# Remove Postfix package

function ee_mod_remove_postfix()
{
	ee_lib_echo "$EE_SECOND Postfix, please wait..."
	$EE_APT_GET $EE_SECOND postfix || ee_lib_error "Unable to $EE_SECOND Postfix, exit status = " $?
}
