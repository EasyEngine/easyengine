# Fix GnuPG key

function ee_lib_gpg_key_fix()
{
	local ee_gpg_key_check

	# GnuPG key check
	$EE_CONFIG_GET system.gpg-key-fix | grep -i true &>> $EE_COMMAND_LOG

	if [ $? -eq 0 ];then

		# Fix GnuPG key problems
		apt-get update > /dev/null 2> /tmp/gpg_key

		for ee_gpg_key_check in $(grep "NO_PUBKEY" /tmp/gpg_key |sed "s/.*NO_PUBKEY //")
		do
			ee_lib_echo "Processing GnuPG key: $ee_gpg_key_check"
			gpg --keyserver subkeys.pgp.net --recv $ee_gpg_key_check \
			&& gpg --export --armor $ee_gpg_key_check \
			| apt-key add -
		done

	fi
}
