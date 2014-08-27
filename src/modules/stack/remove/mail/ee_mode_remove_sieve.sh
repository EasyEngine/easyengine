# Remove Sieve package

function ee_mod_remove_sieve()
{
	ee_lib_echo "$EE_SECOND Sieve package, please wait..."
	$EE_APT_GET $EE_SECOND dovecot-sieve dovecot-managesieved \
	|| ee_lib_error "Unable to $EE_SECOND Sieve, exit status = " $?


}
