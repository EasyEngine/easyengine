# Check GnuPG key

function gpg_key_fix()
{
	local gpg_key_check

	# GnuPG key check
	grep gpg-key-fix /etc/easyengine/ee.conf | grep -i true &>> $EE_LOG

	if [ $? -eq 0 ];then

		# Fix GnuPG key problems
		apt-get update > /dev/null 2> /tmp/gpg_key

		for gpg_key_check in $(grep "NO_PUBKEY" /tmp/gpg_key |sed "s/.*NO_PUBKEY //")
		do
			echo_blue "Processing GnuPG key: $gpg_key_check"
			gpg --keyserver subkeys.pgp.net --recv $gpg_key_check \
			&& gpg --export --armor $gpg_key_check \
			| apt-key add -
		done

	fi
}
